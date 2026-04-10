<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Link;

use Countable;
use Jield\ApiTools\ApiProblem\ApiProblem;
use Jield\ApiTools\Hal\Collection;
use Laminas\Paginator\Paginator;
use Laminas\Stdlib\ArrayUtils;
use Override;
use Traversable;
use function count;
use function is_array;

class PaginationInjector implements PaginationInjectorInterface
{
    #[Override]
    public function injectPaginationLinks(Collection $halCollection): bool|ApiProblem
    {
        $collection = $halCollection->getCollection();
        if (!$collection instanceof Paginator) {
            return false;
        }

        $this->configureCollection(halCollection: $halCollection);

        $pageCount = count(value: $collection);
        if ($pageCount === 0) {
            return true;
        }

        $page = $halCollection->getPage();

        if ($page < 1 || $page > $pageCount) {
            return new ApiProblem(status: 409, detail: 'Invalid page provided');
        }

        $this->injectLinks(halCollection: $halCollection);

        return true;
    }

    private function configureCollection(Collection $halCollection): void
    {
        /** @var Paginator $collection */
        $collection = $halCollection->getCollection();
        $page       = $halCollection->getPage();
        $pageSize   = $halCollection->getPageSize();

        $collection->setItemCountPerPage(itemCountPerPage: $pageSize);
        $collection->setCurrentPageNumber(pageNumber: $page);
    }

    private function injectLinks(Collection $halCollection): void
    {
        $this->injectSelfLink(halCollection: $halCollection);
        $this->injectFirstLink(halCollection: $halCollection);
        $this->injectLastLink(halCollection: $halCollection);
        $this->injectPrevLink(halCollection: $halCollection);
        $this->injectNextLink(halCollection: $halCollection);
    }

    private function injectSelfLink(Collection $halCollection): void
    {
        $page = $halCollection->getPage();
        $link = $this->createPaginationLink(relation: 'self', halCollection: $halCollection, page: $page);
        $halCollection->getLinks()->add(link: $link, overwrite: true);
    }

    private function injectFirstLink(Collection $halCollection): void
    {
        $link = $this->createPaginationLink(relation: 'first', halCollection: $halCollection);
        $halCollection->getLinks()->add(link: $link);
    }

    private function injectLastLink(Collection $halCollection): void
    {
        $page = $this->countCollection(collection: $halCollection->getCollection());
        $link = $this->createPaginationLink(relation: 'last', halCollection: $halCollection, page: $page);
        $halCollection->getLinks()->add(link: $link);
    }

    private function injectPrevLink(Collection $halCollection): void
    {
        $page = $halCollection->getPage();
        $prev = $page > 1 ? $page - 1 : false;

        if ($prev) {
            $link = $this->createPaginationLink(relation: 'prev', halCollection: $halCollection, page: $prev);
            $halCollection->getLinks()->add(link: $link);
        }
    }

    private function injectNextLink(Collection $halCollection): void
    {
        $page      = $halCollection->getPage();
        $pageCount = $this->countCollection(collection: $halCollection->getCollection());
        $next      = $page < $pageCount ? $page + 1 : false;

        if ($next) {
            $link = $this->createPaginationLink(relation: 'next', halCollection: $halCollection, page: $next);
            $halCollection->getLinks()->add(link: $link);
        }
    }

    /**
     * @param string $relation
     * @param int $page
     */
    private function createPaginationLink(string $relation, Collection $halCollection, int $page = 1): Link
    {
        $options = ArrayUtils::merge(
            a: $halCollection->getCollectionRouteOptions(),
            b: ['query' => ['page' => $page]]
        );

        return Link::factory(spec: [
            'rel'   => $relation,
            'route' => [
                'name'    => $halCollection->getCollectionRoute(),
                'params'  => $halCollection->getCollectionRouteParams(),
                'options' => $options,
            ],
        ]);
    }

    /** @param array<mixed>|Traversable|Paginator $collection */
    private function countCollection(mixed $collection): int
    {
        return match (true) {
            $collection instanceof Countable => $collection->count(),
            is_array(value: $collection)     => count(value: $collection),
            default                          => 1,
        };
    }
}
