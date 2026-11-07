<?php

declare(strict_types=1);

namespace Jield\Authorize\Provider\Role;

use Jield\Authorize\Role\Role;
use Jield\Stdlib\Value;
use Laminas\Permissions\Acl\Role\RoleInterface;

use function array_merge;
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

        foreach ($config as $key => $value) {
            if (is_numeric(value: $key)) {
                $roles = array_merge($roles, $this->loadRole(name: Value::string($value)));
            } else {
                $roles = array_merge($roles, $this->loadRole(name: $key, options: Value::array($value)));
            }
        }

        $this->roles = $roles;
    }

    /** @return list<RoleInterface> */
    protected function loadRole(string $name, array $options = [], string|RoleInterface|null $parent = null): array
    {
        $children = Value::array($options['children'] ?? []);

        $roles = [];
        $role = new Role(roleId: $name, parent: $parent);
        $roles[] = $role;

        foreach ($children as $key => $value) {
            if (is_numeric(value: $key)) {
                $roles = array_merge($roles, $this->loadRole(
                    name: Value::string($value),
                    options: [],
                    parent: $role,
                ));
            } else {
                $roles = array_merge($roles, $this->loadRole(
                    name: Value::string($key),
                    options: Value::array($value),
                    parent: $role,
                ));
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
