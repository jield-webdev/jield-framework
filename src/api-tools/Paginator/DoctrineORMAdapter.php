<?php

declare(strict_types=1);

namespace Jield\ApiTools\Paginator;

use Doctrine\ORM\Tools\Pagination\Paginator;
use Jield\ApiTools\Provider\ProviderInterface;
use Laminas\Paginator\Adapter\AdapterInterface;
use Override;
use function array_key_exists;

class DoctrineORMAdapter extends Paginator implements AdapterInterface
{
    public array $cache = [];

    private ProviderInterface $provider;

    public function setProvider(ProviderInterface $provider): DoctrineORMAdapter
    {
        $this->provider = $provider;

        return $this;
    }

    /**
     * @param int $offset
     * @param int $itemCountPerPage
     */
    #[Override]
    public function getItems(int $offset, int $itemCountPerPage): array
    {
        if (
            array_key_exists(key: $offset, array: $this->cache)
            && array_key_exists(key: $itemCountPerPage, array: $this->cache[$offset])
        ) {
            return $this->cache[$offset][$itemCountPerPage];
        }

        $this->getQuery()->setFirstResult(firstResult: $offset);
        $this->getQuery()->setMaxResults(maxResults: $itemCountPerPage);

        if (!array_key_exists(key: $offset, array: $this->cache)) {
            $this->cache[$offset] = [];
        }

        $results = [];
        foreach ($this->getQuery()->getResult() as $result) {
            $results[] = $this->provider->generateArray(entity: $result);
        }

        $this->cache[$offset][$itemCountPerPage] = $results;

        return $this->cache[$offset][$itemCountPerPage];
    }
}