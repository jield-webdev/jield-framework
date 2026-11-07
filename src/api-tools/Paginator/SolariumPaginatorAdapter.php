<?php

declare(strict_types=1);

namespace Jield\ApiTools\Paginator;

use Jield\ApiTools\Provider\SolariumProviderInterface;
use Laminas\Paginator\Adapter\AdapterInterface;
use Override;
use Solarium\Client;
use Solarium\QueryType\Select\Query\Query;

/**
 * Solarium result paginator.
 *
 *
 * @implements \Laminas\Paginator\Adapter\AdapterInterface<array-key, mixed>
 */
class SolariumPaginatorAdapter implements AdapterInterface
{
    protected ?int $count = null;

    public function __construct(
        protected Client $client,
        protected Query $query,
        protected SolariumProviderInterface $provider,
    ) {}

    #[Override]
    public function count(): int
    {
        if (null === $this->count) {
            $query = clone $this->query;
            $query->setStart(0)->setRows(0);
            $result = $this->client->select($query);
            $this->count = $result->getNumFound() ?? 0;
        }

        return $this->count;
    }

    #[Override]
    public function getItems($offset, $itemCountPerPage): iterable
    {
        $this->query->setStart(start: $offset);
        $this->query->setRows(rows: $itemCountPerPage);

        $result = $this->client->select($this->query);
        $this->count = $result->getNumFound() ?? 0;

        $results = [];
        /** @mago-var mixed $result */
        foreach ($result->getIterator() as $result) {
            $results[] = $this->provider->generateArrayFromSearchDocument(document: \Webmozart\Assert\Assert::isInstanceOf($result, \Solarium\QueryType\Select\Result\Document::class));
        }

        return $results;
    }
}
