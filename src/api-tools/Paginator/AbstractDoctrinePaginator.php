<?php

declare(strict_types=1);

namespace Jield\ApiTools\Paginator;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\OffsetPaginator;
use Doctrine\ORM\Tools\Pagination\Window;

/** @implements \IteratorAggregate<int, object> */
abstract class AbstractDoctrinePaginator implements \Countable, \IteratorAggregate
{
    private readonly Query $query;
    private ?bool $useOutputWalkers = null;
    private ?int $totalCount = null;

    public function __construct(Query|QueryBuilder $query, private readonly bool $fetchJoinCollection = true)
    {
        $this->query = $query instanceof QueryBuilder ? $query->getQuery() : $query;
    }

    public function getQuery(): Query
    {
        return $this->query;
    }

    public function getFetchJoinCollection(): bool
    {
        return $this->fetchJoinCollection;
    }

    public function getUseOutputWalkers(): ?bool
    {
        return $this->useOutputWalkers;
    }

    public function setUseOutputWalkers(?bool $useOutputWalkers): static
    {
        $this->useOutputWalkers = $useOutputWalkers;
        $this->totalCount = null;
        return $this;
    }

    /** @return list<object> */
    protected function pageItems(int $offset, int $limit): array
    {
        if ($limit < 1) {
            return [];
        }
        /** @var OffsetPaginator<object> $paginator */
        $paginator = new OffsetPaginator($this->fetchJoinCollection, $this->useOutputWalkers);
        $page = $paginator->paginate($this->query, new Window($offset, $limit));
        $this->totalCount = $page->getTotalCount();
        return $page->getItems();
    }

    public function count(): int
    {
        if ($this->totalCount === null) {
            $this->pageItems(0, 1);
        }
        return $this->totalCount ?? 0;
    }

    /** @return \Traversable<int, object> */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->pageItems($this->query->getFirstResult(), \Jield\Stdlib\Value::int($this->query->getMaxResults())));
    }
}
