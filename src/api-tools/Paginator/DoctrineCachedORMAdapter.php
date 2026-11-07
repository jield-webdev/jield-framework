<?php

declare(strict_types=1);

namespace Jield\ApiTools\Paginator;

use Doctrine\ORM\QueryBuilder;
use Jield\ApiTools\Provider\ProviderInterface;
use Jield\Stdlib\Value;
use Laminas\Cache\Storage\Adapter\AbstractAdapter;
use Laminas\Paginator\Adapter\AdapterInterface;
use Override;

/**
 * @implements \Laminas\Paginator\Adapter\AdapterInterface<int, mixed>
 */
class DoctrineCachedORMAdapter extends AbstractDoctrinePaginator implements AdapterInterface
{
    public function __construct(
        QueryBuilder $query,
        private readonly ProviderInterface $provider,
        private readonly AbstractAdapter $cache,
        private readonly string $cacheKey,
    ) {
        parent::__construct($query, true);
    }

    /**
     * @param int $offset
     * @param int $itemCountPerPage
     */
    #[Override]
    public function getItems($offset, $itemCountPerPage): array
    {
        //Append the offset and itemCountPerPage to the cache key
        $cacheKey = $this->cacheKey . $offset . $itemCountPerPage;

        if (!$this->cache->hasItem($cacheKey)) {
            $this->getQuery()->setFirstResult($offset);
            $this->getQuery()->setMaxResults($itemCountPerPage);

            $results = [];
            /** @var mixed $result */
            foreach ($this->pageItems($offset, $itemCountPerPage) as $result) {
                $results[] = $this->provider->generateArray($result);
            }

            $this->cache->setItem($cacheKey, $results);
        }

        return Value::array($this->cache->getItem($cacheKey));
    }
}
