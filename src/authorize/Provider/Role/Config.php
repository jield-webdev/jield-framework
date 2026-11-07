<?php

declare(strict_types=1);

namespace Jield\Authorize\Provider\Role;

use Jield\Authorize\Role\Role;
use Laminas\Permissions\Acl\Role\RoleInterface;
use Webmozart\Assert\Assert;

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
                $roles = array_merge($roles, $this->loadRole(name: $value));
            } else {
                $roles = array_merge($roles, $this->loadRole(name: $key, options: $value));
            }
        }

        $this->roles = $roles;
    }

    /** @return list<RoleInterface> */
    protected function loadRole(string $name, array $options = [], string|RoleInterface|null $parent = null): array
    {
        $children = Assert::isArray($options['children'] ?? []);

        $roles = [];
        $role = new Role(roleId: $name, parent: $parent);
        $roles[] = $role;

        foreach ($children as $key => $value) {
            if (is_numeric(value: $key)) {
                $roles = array_merge($roles, $this->loadRole(name: $value, options: [], parent: $role));
            } else {
                $roles = array_merge($roles, $this->loadRole(name: $key, options: $value, parent: $role));
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
