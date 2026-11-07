<?php

declare(strict_types=1);

namespace Jield\Authorize\Collector;

use Jield\Authorize\Provider\Identity\ProviderInterface;
use Jield\Mvc\MvcEvent;
use Laminas\Permissions\Acl\Role\RoleInterface;
use Serializable;

use function serialize;
use function unserialize;

/**
 * Role collector - collects the role during dispatch
 */
class RoleCollector implements Serializable
{
    public const NAME = 'bjy_authorize_role_collector';

    public const PRIORITY = 150;

    /** @var array|string[] collected role ids */
    protected array $collectedRoles = [];

    public function __construct(
        protected ProviderInterface $identityProvider,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return static::NAME;
    }

    /**
     * {@inheritDoc}
     */
    public function getPriority(): int
    {
        return static::PRIORITY;
    }

    /**
     * {@inheritDoc}
     */
    public function collect(MvcEvent $mvcEvent): void
    {
        $roles = $this->identityProvider->getIdentityRoles();

        foreach ($roles as $role) {
            if ($role instanceof RoleInterface) {
                $role = $role->getRoleId();
            }

            if ($role) {
                $this->collectedRoles[] = $role;
            }
        }
    }

    /**
     * @return array|string[]
     */
    public function getCollectedRoles(): array
    {
        return $this->collectedRoles;
    }

    /**
     * {@inheritDoc}
     * Retained for callers using the legacy Serializable methods.
     */
    public function serialize(): ?string
    {
        return serialize(value: $this->collectedRoles);
    }

    /**
     * {@inheritDoc}
     * Retained for callers using the legacy Serializable methods.
     */
    public function unserialize($data): void
    {
        $this->collectedRoles = \Jield\Stdlib\Value::array(unserialize(data: $data));
    }

    /**
     * {@inheritDoc}
     */
    public function __serialize()
    {
        return [
            'collectedRoles' => $this->collectedRoles,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function __unserialize(array $serialized)
    {
        $this->collectedRoles = \Jield\Stdlib\Value::array($serialized['collectedRoles']);
    }
}
