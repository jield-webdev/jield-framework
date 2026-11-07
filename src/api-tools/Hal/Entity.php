<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal;

class Entity implements Link\LinkCollectionAwareInterface
{
    use Link\LinkCollectionAwareTrait;

    public function __construct(
        protected object|array $entity,
        protected mixed $id = null,
    ) {
    }

    public function getId(): mixed
    {
        return $this->id;
    }

    public function getEntity(): object|array
    {
        return $this->entity;
    }
}
