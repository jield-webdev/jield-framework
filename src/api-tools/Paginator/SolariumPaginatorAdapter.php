<?php

declare(strict_types=1);

namespace Jield\ApiTools\Paginator;

use Jield\ApiTools\Provider\SolariumProviderInterface;
use Laminas\Paginator\Adapter\AdapterInterface;
use Override;
use Solarium\Client;
use Solarium\Core\Query\Result\ResultInterface;
use Solarium\QueryType\Select\Query\Query;
use Solarium\QueryType\Select\Result\Result;

/**
 * Solarium result paginator.
 */
class SolariumPaginatorAdapter implements AdapterInterface
{
    protected ?int $count = null;

    public function __construct(
        protected Client                    $client,
        protected Query                     $query,
        protected SolariumProviderInterface $provider
    )
    {
    }

    #[Override]
    public function count(): int
    {
        if (null === $this->count) {
            $this->getItems(offset: 0, itemCountPerPage: 0);
        }

        return $this->count;
    }

    #[Override]
    public function getItems($offset, $itemCountPerPage): iterable
    {
        $this->query->setStart(start: $offset);
        $this->query->setRows(rows: $itemCountPerPage);

        $result      = $this->client->select(query: $this->query);
        $this->count = $result->getNumFound();

        $results = [];
        foreach ($result->getIterator() as $result) {
            $results[] = $this->provider->generateArrayFromSearchDocument(document: $result);
        }

        return $results;
    }
}
