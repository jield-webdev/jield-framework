<?php

declare(strict_types=1);

namespace DoctrineORMModule\Paginator\Adapter;

use Doctrine\ORM\Tools\Pagination\Paginator;
use JsonSerializable;
use Laminas\Paginator\Adapter\AdapterInterface;
use Traversable;
use Webmozart\Assert\Assert;

/**
 * Paginator adapter for the Laminas\Paginator component
 *
 * @phpstan-template T of object
 * @template-implements AdapterInterface<array-key, T>
 */
class DoctrinePaginator implements AdapterInterface, JsonSerializable
{
    /**
     * Constructor
     *
     * @param Paginator<T> $paginator
     */
    public function __construct(
        protected Paginator $paginator,
    ) {}

    /** @param Paginator<T> $paginator */
    public function setPaginator(Paginator $paginator): self
    {
        $this->paginator = $paginator;

        return $this;
    }

    /** @return Paginator<T> */
    public function getPaginator(): Paginator
    {
        return $this->paginator;
    }

    /**
     * {@inheritDoc}
     *
     * @param int|null $offset
     * @param int|null $itemCountPerPage
     *
     * @return Traversable<array-key, T>
     */
    public function getItems($offset, $itemCountPerPage): Traversable
    {
        $this->paginator
            ->getQuery()
            ->setFirstResult(firstResult: Assert::integer($offset))
            ->setMaxResults(maxResults: $itemCountPerPage);

        return $this->paginator->getIterator();
    }

    public function count(): int
    {
        return $this->paginator->count();
    }

    /** @return array{select: list<string>|string, count_select: int} */
    public function jsonSerialize(): array
    {
        return [
            'select' => $this->paginator->getQuery()->getSQL(),
            'count_select' => $this->paginator->count(),
        ];
    }
}
