<?php

declare(strict_types=1);

namespace Jield\Authorize\Service;

use Closure;
use Jield\Authorize\Guard\GuardInterface;
use Jield\Authorize\Provider\Identity\AuthenticationIdentityProvider;
use Jield\Authorize\Provider\Identity\ProviderInterface as IdentityProvider;
use Jield\Authorize\Provider\Resource\ProviderInterface as ResourceProvider;
use Jield\Authorize\Provider\Role\ProviderInterface as RoleProvider;
use Jield\Authorize\Provider\Rule\ProviderInterface as RuleProvider;
use Jield\Authorize\Role\Role;
use Jield\Authorize\Role\UserAsRole;
use Jield\Authorize\Config;
use Jield\Authorize\Cache;
use Jield\Authorize\Guards;
use Jield\Authorize\RoleProviders;
use Jield\Authorize\ResourceProviders;
use Jield\Authorize\RuleProviders;
use Laminas\Cache\Storage\Adapter\AbstractAdapter;
use Laminas\Permissions\Acl\Acl;
use Laminas\Permissions\Acl\Exception\InvalidArgumentException;
use Laminas\Permissions\Acl\Resource\GenericResource;
use Laminas\Permissions\Acl\Resource\ResourceInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Traversable;
use function count;
use function is_array;
use function is_int;
use function is_string;
use function print_r;


/**
 * Authorize service
 */
class AuthorizeService
{
    public const string TYPE_ALLOW = 'allow';

    public const string TYPE_DENY = 'deny';

    protected ?Acl $acl = null;

    private array $rulesWithAssertions = [];

    /** @var RoleProvider[] */
    protected array $roleProviders = [];

    /** @var ResourceProvider[] */
    protected array $resourceProviders = [];

    /** @var RuleProvider[] */
    protected array $ruleProviders = [];

    protected IdentityProvider $identityProvider;

    /** @var GuardInterface[] */
    protected array $guards = [];

    protected ?Closure $loaded = null;

    private AbstractAdapter $cache;

    private AuthenticationIdentityProvider $authenticationIdentityProvider;

    /** @var array */
    protected array $config;

    /**
     * @param array $config
     */
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __construct(private readonly ContainerInterface $container)
    {
        $this->authenticationIdentityProvider = $container->get(AuthenticationIdentityProvider::class);
        $this->config = $container->get(Config::class);

        $cacheEnabled = $this->config['cache_enabled'] ?? false;
        if ($cacheEnabled) {
            /** @var AbstractAdapter $cache */
            $cache       = $container->get(Cache::class);
            $this->cache = $cache;
        }
    }

    public function addRoleProvider(RoleProvider $provider): static
    {
        $this->loaded && $this->loaded->__invoke();

        $this->roleProviders[] = $provider;

        return $this;
    }

    public function addResourceProvider(ResourceProvider $provider): static
    {
        $this->loaded && $this->loaded->__invoke();

        $this->resourceProviders[] = $provider;

        return $this;
    }

    public function addRuleProvider(RuleProvider $provider): static
    {
        $this->loaded && $this->loaded->__invoke();

        $this->ruleProviders[] = $provider;

        return $this;
    }

    public function setIdentityProvider(IdentityProvider $provider): static
    {
        $this->loaded && $this->loaded->__invoke();

        $this->identityProvider = $provider;

        return $this;
    }

    public function getIdentityProvider(): IdentityProvider
    {
        $this->loaded && $this->loaded->__invoke();

        return $this->identityProvider;
    }

    public function addGuard(GuardInterface $guard): static
    {
        $this->loaded && $this->loaded->__invoke();

        $this->guards[] = $guard;

        if ($guard instanceof ResourceProvider) {
            $this->addResourceProvider(provider: $guard);
        }

        if ($guard instanceof RuleProvider) {
            $this->addRuleProvider(provider: $guard);
        }

        return $this;
    }

    public function getGuards(): array
    {
        $this->loaded && $this->loaded->__invoke();

        return $this->guards;
    }

    /**
     * @return Acl
     */
    public function getAcl(): Acl
    {
        $this->loaded && $this->loaded->__invoke();

        return $this->acl;
    }

    /**
     * @param string|ResourceInterface $resource
     * @param string|null $privilege
     * @return bool
     */
    public function isAllowed(ResourceInterface|string $resource, ?string $privilege = null): bool
    {
        $this->loaded && $this->loaded->__invoke();

        try {
            return $this->acl->isAllowed(role: $this->getIdentity(), resource: $resource, privilege: $privilege);
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public function load(): void
    {
        if (null === $this->loaded) {
            return;
        }

        $this->loaded = null;

        $aclKacheKey         = sprintf('%s_acl', $this->config['cache_key']);
        $rulesWithAssertions = sprintf('%s_rules_with_assertion', $this->config['cache_key']);

        $success      = false;
        $cacheEnabled = $this->config['cache_enabled'] ?? false;
        if ($cacheEnabled) {
            $this->rulesWithAssertions = $this->cache->getItem($rulesWithAssertions, $success) ?? [];
            $this->acl                 = $this->cache->getItem($aclKacheKey, $success);
        }

        if (!$this->acl instanceof Acl || !$success) {
            $this->loadAcl();
            if ($cacheEnabled) {
                $this->cache->setItem($aclKacheKey, $this->acl);
                $this->cache->setItem($rulesWithAssertions, $this->rulesWithAssertions);
            }
        }

        //$this->setIdentityProvider($this->authenticationIdentityProvider);
        $parentRoles = $this->authenticationIdentityProvider->getIdentityRoles();

        $this->acl->addRole($this->getIdentityAsRole(), $parentRoles);
    }

    protected function addRoles($roles): void
    {
        if (!is_array(value: $roles) && !$roles instanceof Traversable) {
            $roles = [$roles];
        }

        /** @var Role $role */
        foreach ($roles as $role) {
            if ($this->acl->hasRole(role: $role)) {
                continue;
            }

            if ($role->getParent() !== null) {
                $this->addRoles(roles: [$role->getParent()]);
                $this->acl->addRole(role: $role, parents: $role->getParent());
            } elseif (!$this->acl->hasRole(role: $role)) {
                $this->acl->addRole(role: $role);
            }
        }
    }

    protected function loadResource($resources, $parent = null): void
    {
        if (!is_array(value: $resources) && !$resources instanceof Traversable) {
            throw new \InvalidArgumentException(message: 'Resources argument must be traversable: ' . print_r(value: $resources, return: true));
        }

        foreach ($resources as $key => $value) {
            if ($value instanceof ResourceInterface) {
                $key = $value;
            } elseif (is_string(value: $key)) {
                $key = new GenericResource(resourceId: $key);
            } elseif (is_int(value: $key)) {
                $key = new GenericResource(resourceId: $value);
            }

            if (is_iterable(value: $value)) {
                $this->acl->addResource(resource: $key, parent: $parent);
                $this->loadResource(resources: $value, parent: $key);
            } elseif (!$this->acl->hasResource(resource: $key)) {
                $this->acl->addResource(resource: $key, parent: $parent);
            }
        }
    }

    protected function loadRule(array $rule, $type): void
    {
        $privileges = null;
        $ruleSize   = count(value: $rule);

        if (4 === $ruleSize) {
            [$roles, $resources, $privileges, $assertion] = $rule;
            $this->rulesWithAssertions[$resources] = $assertion;
        } elseif (3 === $ruleSize) {
            [$roles, $resources, $privileges] = $rule;
        } elseif (2 === $ruleSize) {
            [$roles, $resources] = $rule;
        } else {
            throw new \InvalidArgumentException(message: 'Invalid rule definition: ' . print_r(value: $rule, return: true));
        }

        //We only do allow rules
        $this->acl->allow($roles, $resources, $privileges);
    }

    public function getIdentityAsRole(): UserAsRole
    {
        return $this->authenticationIdentityProvider->getIdentityAsRole();
    }

    /**
     * This function is very important and overrides the default "bjyauthorize-identity".
     */
    public function getIdentity(): UserAsRole
    {
        return $this->authenticationIdentityProvider->getIdentityAsRole();
    }

    public function getIdentityRoles(): array
    {
        return $this->authenticationIdentityProvider->getIdentityRoles();
    }

    private function loadAcl(): void
    {
        $this->acl = new Acl();

        //Set the role provider first
        foreach ($this->container->get(RoleProviders::class) as $provider) {
            $this->addRoleProvider(provider: $provider);
        }

        foreach ($this->roleProviders as $provider) {
            $this->addRoles(roles: $provider->getRoles());
        }

        foreach ($this->container->get(Guards::class) as $guard) {
            $this->addGuard(guard: $guard);
        }

        foreach ($this->container->get(ResourceProviders::class) as $provider) {
            $this->addResourceProvider(provider: $provider);
        }

        foreach ($this->container->get(RuleProviders::class) as $provider) {
            $this->addRuleProvider(provider: $provider);
        }

        foreach ($this->resourceProviders as $provider) {
            $this->loadResource(resources: $provider->getResources(), parent: null);
        }

        foreach ($this->ruleProviders as $provider) {
            $rules = $provider->getRules();

            if (isset($rules['allow'])) {
                foreach ($rules['allow'] as $rule) {
                    $this->loadRule(rule: $rule, type: static::TYPE_ALLOW);
                }
            }
        }
    }
}
