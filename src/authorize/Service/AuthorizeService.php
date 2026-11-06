<?php

declare(strict_types=1);

namespace Jield\Authorize\Service;

use Closure;
use Jield\Authorize\Cache;
use Jield\Authorize\Config;
use Jield\Authorize\Guard\GuardInterface;
use Jield\Authorize\Guards;
use Jield\Authorize\Provider\Identity\AuthenticationIdentityProvider;
use Jield\Authorize\Provider\Identity\ProviderInterface as IdentityProvider;
use Jield\Authorize\Provider\Resource\ProviderInterface as ResourceProvider;
use Jield\Authorize\Provider\Role\ProviderInterface as RoleProvider;
use Jield\Authorize\Provider\Rule\ProviderInterface as RuleProvider;
use Jield\Authorize\ResourceProviders;
use Jield\Authorize\Role\Role;
use Jield\Authorize\Role\UserAsRole;
use Jield\Authorize\RoleProviders;
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
     
 *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __construct(
        private readonly ContainerInterface $container,
    ) {
        $this->authenticationIdentityProvider = \Jield\Stdlib\Service::get($container, AuthenticationIdentityProvider::class);
        $this->config = \Jield\Stdlib\Value::array($container->get(Config::class));
        $this->loaded = $this->load(...);

        /** @var mixed $cacheEnabled */
        $cacheEnabled = $this->config['cache_enabled'] ?? false;
        if ($cacheEnabled) {
            /** @var AbstractAdapter $cache */
            $cache = \Jield\Stdlib\Service::get($container, Cache::class);
            $this->cache = $cache;
        }
    }

    public function addRoleProvider(RoleProvider $provider): static
    {
        $this->loaded && \Jield\Stdlib\Value::truthy($this->loaded->__invoke());

        $this->roleProviders[] = $provider;

        return $this;
    }

    public function addResourceProvider(ResourceProvider $provider): static
    {
        $this->loaded && \Jield\Stdlib\Value::truthy($this->loaded->__invoke());

        $this->resourceProviders[] = $provider;

        return $this;
    }

    public function addRuleProvider(RuleProvider $provider): static
    {
        $this->loaded && \Jield\Stdlib\Value::truthy($this->loaded->__invoke());

        $this->ruleProviders[] = $provider;

        return $this;
    }

    public function setIdentityProvider(IdentityProvider $provider): static
    {
        $this->loaded && \Jield\Stdlib\Value::truthy($this->loaded->__invoke());

        $this->identityProvider = $provider;

        return $this;
    }

    public function getIdentityProvider(): IdentityProvider
    {
        $this->loaded && \Jield\Stdlib\Value::truthy($this->loaded->__invoke());

        return $this->identityProvider;
    }

    public function addGuard(GuardInterface $guard): static
    {
        $this->loaded && \Jield\Stdlib\Value::truthy($this->loaded->__invoke());

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
        $this->loaded && \Jield\Stdlib\Value::truthy($this->loaded->__invoke());

        return $this->guards;
    }

    /**
     * @return Acl
     */
    public function getAcl(): Acl
    {
        $this->loaded && \Jield\Stdlib\Value::truthy($this->loaded->__invoke());

        return \Jield\Stdlib\Value::instance($this->acl, \Laminas\Permissions\Acl\Acl::class);
    }

    /**
     * @param string|ResourceInterface $resource
     * @param string|null $privilege
     * @return bool
     */
    public function isAllowed(ResourceInterface|string $resource, ?string $privilege = null): bool
    {
        $this->loaded && \Jield\Stdlib\Value::truthy($this->loaded->__invoke());

        try {
            return \Jield\Stdlib\Value::present($this->acl)->isAllowed(role: $this->getIdentity(), resource: $resource, privilege: $privilege);
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

        $aclKacheKey = sprintf('%s_acl', \Jield\Stdlib\Value::scalar($this->config['cache_key']));
        $rulesWithAssertions = sprintf('%s_rules_with_assertion', \Jield\Stdlib\Value::scalar($this->config['cache_key']));

        $success = false;
        /** @var mixed $cacheEnabled */
        $cacheEnabled = $this->config['cache_enabled'] ?? false;
        if ($cacheEnabled) {
            $this->rulesWithAssertions = \Jield\Stdlib\Value::array($this->cache->getItem($rulesWithAssertions, $success) ?? []);
            $this->acl = \Jield\Stdlib\Value::nullableInstance($this->cache->getItem($aclKacheKey, $success), \Laminas\Permissions\Acl\Acl::class);
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

        \Jield\Stdlib\Value::present($this->acl)->addRole($this->getIdentityAsRole(), $parentRoles);
    }

    protected function addRoles($roles): void
    {
        if (!is_array(value: $roles) && !$roles instanceof Traversable) {
            $roles = [$roles];
        }

        /** @var Role $role */
        foreach ($roles as $role) {
            if (\Jield\Stdlib\Value::present($this->acl)->hasRole(role: $role)) {
                continue;
            }

            if ($role->getParent() !== null) {
                $this->addRoles(roles: [$role->getParent()]);
                \Jield\Stdlib\Value::present($this->acl)->addRole(role: $role, parents: $role->getParent());
            } elseif (!\Jield\Stdlib\Value::present($this->acl)->hasRole(role: $role)) {
                \Jield\Stdlib\Value::present($this->acl)->addRole(role: $role);
            }
        }
    }

    protected function loadResource($resources, $parent = null): void
    {
        if (!is_array(value: $resources) && !$resources instanceof Traversable) {
            throw new \InvalidArgumentException(
                message: 'Resources argument must be traversable: ' . print_r(value: $resources, return: true),
            );
        }

        /** @var mixed $key
         * @var mixed $value
         */
        foreach ($resources as $key => $value) {
            if ($value instanceof ResourceInterface) {
                $key = $value;
            } elseif (is_string(value: $key)) {
                $key = new GenericResource(resourceId: $key);
            } elseif (is_int(value: $key)) {
                $key = new GenericResource(resourceId: \Jield\Stdlib\Value::string($value));
            }

            if (is_iterable(value: $value)) {
                \Jield\Stdlib\Value::present($this->acl)->addResource(resource: \Jield\Stdlib\Value::instanceOrString($key, \Laminas\Permissions\Acl\Resource\ResourceInterface::class), parent: \Jield\Stdlib\Value::nullableInstanceOrString($parent, \Laminas\Permissions\Acl\Resource\ResourceInterface::class));
                $this->loadResource(resources: $value, parent: $key);
            } elseif (!\Jield\Stdlib\Value::present($this->acl)->hasResource(resource: \Jield\Stdlib\Value::instanceOrString($key, \Laminas\Permissions\Acl\Resource\ResourceInterface::class))) {
                \Jield\Stdlib\Value::present($this->acl)->addResource(resource: \Jield\Stdlib\Value::instanceOrString($key, \Laminas\Permissions\Acl\Resource\ResourceInterface::class), parent: \Jield\Stdlib\Value::nullableInstanceOrString($parent, \Laminas\Permissions\Acl\Resource\ResourceInterface::class));
            }
        }
    }

    protected function loadRule(array $rule, $type): void
    {
        $privileges = null;
        $ruleSize = count(value: $rule);

        if (4 === $ruleSize) {
            /** @var mixed $roles
             * @var mixed $resources
             * @var mixed $privileges
             * @var mixed $assertion
             */
            [$roles, $resources, $privileges, $assertion] = $rule;
            $this->rulesWithAssertions[\Jield\Stdlib\Value::key($resources)] = $assertion;
        } elseif (3 === $ruleSize) {
            /** @var mixed $roles
             * @var mixed $resources
             * @var mixed $privileges
             */
            [$roles, $resources, $privileges] = $rule;
        } elseif (2 === $ruleSize) {
            /** @var mixed $roles
             * @var mixed $resources
             */
            [$roles, $resources] = $rule;
        } else {
            throw new \InvalidArgumentException(
                message: 'Invalid rule definition: ' . print_r(value: $rule, return: true),
            );
        }

        //We only do allow rules
        \Jield\Stdlib\Value::present($this->acl)->allow(\Jield\Stdlib\Value::instanceOrArrayOrString($roles, \Laminas\Permissions\Acl\Role\RoleInterface::class), \Jield\Stdlib\Value::instanceOrArrayOrString($resources, \Laminas\Permissions\Acl\Resource\ResourceInterface::class), \Jield\Stdlib\Value::nullableArrayOrString($privileges));
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
        /** @var mixed $provider */
        foreach (\Jield\Stdlib\Value::array($this->container->get(RoleProviders::class)) as $provider) {
            $this->addRoleProvider(provider: \Jield\Stdlib\Value::instance($provider, \Jield\Authorize\Provider\Role\ProviderInterface::class));
        }

        foreach ($this->roleProviders as $provider) {
            $this->addRoles(roles: $provider->getRoles());
        }

        /** @var mixed $guard */
        foreach (\Jield\Stdlib\Value::array($this->container->get(Guards::class)) as $guard) {
            $this->addGuard(guard: \Jield\Stdlib\Value::instance($guard, \Jield\Authorize\Guard\GuardInterface::class));
        }

        /** @var mixed $provider */
        foreach (\Jield\Stdlib\Value::array($this->container->get(ResourceProviders::class)) as $provider) {
            $this->addResourceProvider(provider: \Jield\Stdlib\Value::instance($provider, \Jield\Authorize\Provider\Resource\ProviderInterface::class));
        }

        /** @var mixed $provider */
        foreach (\Jield\Stdlib\Value::array($this->container->get(RuleProviders::class)) as $provider) {
            $this->addRuleProvider(provider: \Jield\Stdlib\Value::instance($provider, \Jield\Authorize\Provider\Rule\ProviderInterface::class));
        }

        foreach ($this->resourceProviders as $provider) {
            $this->loadResource(resources: $provider->getResources(), parent: null);
        }

        foreach ($this->ruleProviders as $provider) {
            $rules = $provider->getRules();

            if (isset($rules['allow'])) {
                /** @var mixed $rule */
                foreach (\Jield\Stdlib\Value::iterable($rules['allow']) as $rule) {
                    $this->loadRule(rule: \Jield\Stdlib\Value::array($rule), type: static::TYPE_ALLOW);
                }
            }
        }
    }
}
