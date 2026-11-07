<?php

declare(strict_types=1);

namespace Jield\Search\Paginator\Adapter;

use Jield\Stdlib\Value;
use Laminas\Paginator\Adapter\AdapterInterface;
use Solarium\Client;
use Solarium\QueryType\Select\Query\Query;
use Solarium\QueryType\Select\Result\Result;

/**
 * Solarium result paginator.
 *
 *
 * @implements \Laminas\Paginator\Adapter\AdapterInterface<array-key, mixed>
 */
class SolariumPaginator implements AdapterInterface
{
    protected ?int $count = null;

    public function __construct(
        protected Client $client,
        protected Query $query,
    ) {}

    public function count(): int
    {
        if (null === $this->count) {
            $query = clone $this->query;
            $query->setStart(0)->setRows(0);
            $result = $this->client->select($query);
            $this->count =
                Value::instance($result, Result::class)->getNumFound()
                ?? 0;
        }

        return $this->count;
    }

    public function getItems($offset, $itemCountPerPage): iterable
    {
        $this->query->setStart(start: $offset);
        $this->query->setRows(rows: $itemCountPerPage);
        $result = $this->client->select($this->query);
        $this->count =
            Value::instance($result, Result::class)->getNumFound() ?? 0;

        return $result->getIterator();
    }
}
