<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal;

use Laminas\Paginator\Paginator;

class Entity implements Link\LinkCollectionAwareInterface
{
    use Link\LinkCollectionAwareTrait;

    protected iterable|Paginator $entity = [];

    public function __construct(Paginator|iterable $entity, protected ?int $id = null)
    {
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
