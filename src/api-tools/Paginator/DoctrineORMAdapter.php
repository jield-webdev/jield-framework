<?php

declare(strict_types=1);

namespace Jield\ApiTools\Paginator;

use Jield\ApiTools\Provider\ProviderInterface;
use Jield\Stdlib\Value;
use Laminas\Paginator\Adapter\AdapterInterface;
use Override;

use function array_key_exists;

/**
 * @implements \Laminas\Paginator\Adapter\AdapterInterface<int, mixed>
 */
class DoctrineORMAdapter extends AbstractDoctrinePaginator implements AdapterInterface
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
    public function getItems($offset, $itemCountPerPage): array
    {
        if (array_key_exists($offset, $this->cache) && array_key_exists($itemCountPerPage, \Webmozart\Assert\Assert::isArray($this->cache[$offset]))) {
            return \Webmozart\Assert\Assert::isArray($this->cache[$offset][$itemCountPerPage]);
        }

        $this->getQuery()->setFirstResult($offset);
        $this->getQuery()->setMaxResults($itemCountPerPage);

        if (!array_key_exists($offset, $this->cache)) {
            $this->cache[$offset] = [];
        }

        $results = [];
        foreach ($this->pageItems($offset, $itemCountPerPage) as $result) {
            $results[] = $this->provider->generateArray($result);
        }

        Value::setPath($this->cache, [$offset, $itemCountPerPage], $results);

        return \Webmozart\Assert\Assert::isArray(Value::accessible($this->cache[$offset])[$itemCountPerPage]);
    }
}
