<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal;

use Laminas\Paginator\Paginator;

class Entity implements Link\LinkCollectionAwareInterface
{
    use Link\LinkCollectionAwareTrait;

    protected object|array $entity = [];

    public function __construct(
        object|array $entity,
        protected mixed $id = null,
    ) {
        $this->entity = $entity;
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
