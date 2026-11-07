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
use Jield\Stdlib\Service;
use Jield\Stdlib\Value;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\Permissions\Acl\Acl;
use Laminas\Permissions\Acl\Exception\InvalidArgumentException;
use Laminas\Permissions\Acl\Resource\GenericResource;
use Laminas\Permissions\Acl\Resource\ResourceInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Traversable;
use Webmozart\Assert\Assert;

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

    private StorageInterface $cache;

    private readonly AuthenticationIdentityProvider $authenticationIdentityProvider;

    protected array $config;

    /**
     * @throws ContainerExceptionInterface
     */
    public function __construct(
        private readonly ContainerInterface $container,
    ) {
        $this->authenticationIdentityProvider = Service::get($container, AuthenticationIdentityProvider::class);
        $this->config = Service::config($container, Config::class);
        $this->loaded = $this->load(...);

        /** @mago-var mixed $cacheEnabled */
        $cacheEnabled = $this->config['cache_enabled'] ?? false;
        if ($cacheEnabled) {
            $cache = Assert::isInstanceOf($container->get(Cache::class), StorageInterface::class);
            $this->cache = $cache;
        }
    }

    public function addRoleProvider(RoleProvider $provider): static
    {
        $this->loaded && Value::truthy($this->loaded->__invoke());

        $this->roleProviders[] = $provider;

        return $this;
    }

    public function addResourceProvider(ResourceProvider $provider): static
    {
        $this->loaded && Value::truthy($this->loaded->__invoke());

        $this->resourceProviders[] = $provider;

        return $this;
    }

    public function addRuleProvider(RuleProvider $provider): static
    {
        $this->loaded && Value::truthy($this->loaded->__invoke());

        $this->ruleProviders[] = $provider;

        return $this;
    }

    public function setIdentityProvider(IdentityProvider $provider): static
    {
        $this->loaded && Value::truthy($this->loaded->__invoke());

        $this->identityProvider = $provider;

        return $this;
    }

    public function getIdentityProvider(): IdentityProvider
    {
        $this->loaded && Value::truthy($this->loaded->__invoke());

        return $this->identityProvider;
    }

    public function addGuard(GuardInterface $guard): static
    {
        $this->loaded && Value::truthy($this->loaded->__invoke());

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
        $this->loaded && Value::truthy($this->loaded->__invoke());

        return $this->guards;
    }

    public function getAcl(): Acl
    {
        $this->loaded && Value::truthy($this->loaded->__invoke());

        return Assert::isInstanceOf($this->acl, \Laminas\Permissions\Acl\Acl::class);
    }

    public function isAllowed(ResourceInterface|string $resource, ?string $privilege = null): bool
    {
        $this->loaded && Value::truthy($this->loaded->__invoke());

        try {
            return Assert::notNull($this->acl)->isAllowed(
                role: $this->getIdentity(),
                resource: $resource,
                privilege: $privilege,
            );
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * @throws \Laminas\Cache\Exception\ExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function load(): void
    {
        if (null === $this->loaded) {
            return;
        }

        $this->loaded = null;

        $aclKacheKey = sprintf('%s_acl', Value::scalar($this->config['cache_key']));
        $rulesWithAssertions = sprintf('%s_rules_with_assertion', Value::scalar($this->config['cache_key']));

        $success = false;
        /** @mago-var mixed $cacheEnabled */
        $cacheEnabled = $this->config['cache_enabled'] ?? false;
        if ($cacheEnabled) {
            $this->rulesWithAssertions = Assert::isArray($this->cache->getItem($rulesWithAssertions, $success) ?? []);
            $this->acl = Value::nullableInstance($this->cache->getItem($aclKacheKey, $success), \Laminas\Permissions\Acl\Acl::class);
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

        Assert::notNull($this->acl)->addRole($this->getIdentityAsRole(), $parentRoles);
    }

    protected function addRoles($roles): void
    {
        if (!is_array(value: $roles) && !$roles instanceof Traversable) {
            $roles = [$roles];
        }

        /** @var Role $role */
        foreach ($roles as $role) {
            if (Assert::notNull($this->acl)->hasRole(role: $role)) {
                continue;
            }

            if ($role->getParent() !== null) {
                $this->addRoles(roles: [$role->getParent()]);
                Assert::notNull($this->acl)->addRole(role: $role, parents: $role->getParent());
            } elseif (!Assert::notNull($this->acl)->hasRole(role: $role)) {
                Assert::notNull($this->acl)->addRole(role: $role);
            }
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function loadResource($resources, $parent = null): void
    {
        if (!is_array(value: $resources) && !$resources instanceof Traversable) {
            throw new \InvalidArgumentException(
                message: 'Resources argument must be traversable: ' . print_r(value: $resources, return: true),
            );
        }

        /**
         * @mago-var mixed $key
         * @mago-var mixed $value
         */
        foreach ($resources as $key => $value) {
            if ($value instanceof ResourceInterface) {
                $key = $value;
            } elseif (is_string(value: $key)) {
                $key = new GenericResource(resourceId: $key);
            } elseif (is_int(value: $key)) {
                $key = new GenericResource(resourceId: Assert::string($value));
            }

            if (is_iterable(value: $value)) {
                Assert::notNull($this->acl)->addResource(resource: Value::instanceOrString($key, \Laminas\Permissions\Acl\Resource\ResourceInterface::class), parent: Value::nullableInstanceOrString($parent, \Laminas\Permissions\Acl\Resource\ResourceInterface::class));
                $this->loadResource(resources: $value, parent: $key);
            } elseif (!Assert::notNull($this->acl)->hasResource(resource: Value::instanceOrString($key, \Laminas\Permissions\Acl\Resource\ResourceInterface::class))) {
                Assert::notNull($this->acl)->addResource(resource: Value::instanceOrString($key, \Laminas\Permissions\Acl\Resource\ResourceInterface::class), parent: Value::nullableInstanceOrString($parent, \Laminas\Permissions\Acl\Resource\ResourceInterface::class));
            }
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function loadRule(array $rule, $type): void
    {
        $privileges = null;
        $ruleSize = count(value: $rule);

        if (4 === $ruleSize) {
            /**
             * @mago-var mixed $roles
             * @mago-var mixed $resources
             * @mago-var mixed $privileges
             * @mago-var mixed $assertion
             */
            [$roles, $resources, $privileges, $assertion] = $rule;
            $this->rulesWithAssertions[Value::key($resources)] = $assertion;
        } elseif (3 === $ruleSize) {
            /**
             * @mago-var mixed $roles
             * @mago-var mixed $resources
             * @mago-var mixed $privileges
             */
            [$roles, $resources, $privileges] = $rule;
        } elseif (2 === $ruleSize) {
            /**
             * @mago-var mixed $roles
             * @mago-var mixed $resources
             */
            [$roles, $resources] = $rule;
        } else {
            throw new \InvalidArgumentException(
                message: 'Invalid rule definition: ' . print_r(value: $rule, return: true),
            );
        }

        //We only do allow rules
        Assert::notNull($this->acl)->allow(Value::instanceOrArrayOrString($roles, \Laminas\Permissions\Acl\Role\RoleInterface::class), Value::instanceOrArrayOrString($resources, \Laminas\Permissions\Acl\Resource\ResourceInterface::class), Value::nullableArrayOrString($privileges));
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

    /**
     * @throws ContainerExceptionInterface
     */
    private function loadAcl(): void
    {
        $this->acl = new Acl();

        //Set the role provider first
        /** @mago-var mixed $provider */
        foreach (Assert::isArray($this->container->get(RoleProviders::class)) as $provider) {
            $this->addRoleProvider(provider: Assert::isInstanceOf($provider, \Jield\Authorize\Provider\Role\ProviderInterface::class));
        }

        foreach ($this->roleProviders as $provider) {
            $this->addRoles(roles: $provider->getRoles());
        }

        /** @mago-var mixed $guard */
        foreach (Assert::isArray($this->container->get(Guards::class)) as $guard) {
            $this->addGuard(guard: Assert::isInstanceOf($guard, \Jield\Authorize\Guard\GuardInterface::class));
        }

        /** @mago-var mixed $provider */
        foreach (Assert::isArray($this->container->get(ResourceProviders::class)) as $provider) {
            $this->addResourceProvider(provider: Assert::isInstanceOf($provider, \Jield\Authorize\Provider\Resource\ProviderInterface::class));
        }

        /** @mago-var mixed $provider */
        foreach (Assert::isArray($this->container->get(RuleProviders::class)) as $provider) {
            $this->addRuleProvider(provider: Assert::isInstanceOf($provider, \Jield\Authorize\Provider\Rule\ProviderInterface::class));
        }

        foreach ($this->resourceProviders as $provider) {
            $this->loadResource(resources: $provider->getResources(), parent: null);
        }

        foreach ($this->ruleProviders as $provider) {
            $rules = $provider->getRules();

            if (isset($rules['allow'])) {
                /** @mago-var mixed $rule */
                foreach (Assert::isIterable($rules['allow']) as $rule) {
                    $this->loadRule(rule: Assert::isArray($rule), type: static::TYPE_ALLOW);
                }
            }
        }
    }
}
