<?php

declare(strict_types=1);

namespace Jield\Authorize\Provider\Role;

use Jield\Authorize\Role\Role;
use Laminas\Permissions\Acl\Role\RoleInterface;

use function array_merge;
use function count;
use function is_numeric;

/**
 * Array config based Role provider
 */
class Config implements ProviderInterface
{
    /** @var RoleInterface[] */
    protected array $roles = [];

    /**
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $roles = [];

        /** @var mixed $value */
        foreach ($config as $key => $value) {
            if (is_numeric(value: $key)) {
                $roles = array_merge($roles, $this->loadRole(name: \Jield\Stdlib\Value::string($value)));
            } else {
                $roles = array_merge($roles, $this->loadRole(name: $key, options: \Jield\Stdlib\Value::array($value)));
            }
        }

        $this->roles = $roles;
    }

    protected function loadRole(string $name, array $options = [], string|RoleInterface|null $parent = null): array
    {
        $children = isset($options['children']) && count(value: \Jield\Stdlib\Value::countable($options['children'])) > 0 ? $options['children'] : [];

        $roles = [];
        $role = new Role(roleId: $name, parent: $parent);
        $roles[] = $role;

        /** @var mixed $value */
        foreach ($children as $key => $value) {
            if (is_numeric(value: $key)) {
                $roles = array_merge($roles, $this->loadRole(name: \Jield\Stdlib\Value::string($value), options: [], parent: $role));
            } else {
                $roles = array_merge($roles, $this->loadRole(name: \Jield\Stdlib\Value::string($key), options: \Jield\Stdlib\Value::array($value), parent: $role));
            }
        }

        return $roles;
    }

    /**
     * {@inheritDoc}
     */
    public function getRoles(): array
    {
        return $this->roles;
    }
}
