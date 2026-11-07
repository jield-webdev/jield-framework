<?php

declare(strict_types=1);

namespace Jield\Search\Controller\Plugin;

use Doctrine\Common\Collections\Order;
use JetBrains\PhpStorm\Pure;
use Jield\Mvc\Application;
use Jield\Mvc\Controller\Plugin\AbstractPlugin;
use Jield\Search\ValueObject\DateInterval;
use Jield\Search\ValueObject\SearchFormResult;
use Jield\Stdlib\Value;
use Laminas\Http\Request;
use Laminas\Stdlib\RequestInterface;
use Psr\Container\ContainerInterface;

use function http_build_query;
use function urldecode;
use function urlencode;

class GetFilter extends AbstractPlugin
{
    private SearchFormResult $filter;

    protected ?string $encodedFilter = null;

    private readonly Request|RequestInterface $request;

    public function __construct(
        private readonly ContainerInterface $container,
    ) {
        /** @var Application $application */
        $application = $this->container->get('application');

        $this->encodedFilter = urldecode(
            string: (string) $application->getMvcEvent()->getRouteMatch()?->getParam(name: 'encodedFilter'),
        );

        /** @var Request $request */
        $this->request = $application->getMvcEvent()->getRequest();
    }

    public function __invoke(): GetFilter
    {
        //Make local variables
        $request = $this->request;
        $encodedFilter = $this->encodedFilter;

        //Initiate the filter
        $this->filter = new SearchFormResult(
            order: Value::string(Value::instance(
                $request,
                \Laminas\Http\Request::class,
            )->getQuery(name: 'order', default: 'default')),
            direction: Value::string(Value::instance(
                $request,
                \Laminas\Http\Request::class,
            )->getQuery(name: 'direction', default: Order::Ascending->value)),
        );

        if (!empty($encodedFilter)) {
            $this->filter->updateFromEncodedFilter(encodedFilter: $encodedFilter);
        }

        // If the form is submitted, refresh the URL
        if (Value::instance($request, \Laminas\Http\Request::class)->getQuery(name: 'query') !== null) {
            $this->filter->setQuery(query: Value::nullableString(Value::instance(
                $request,
                \Laminas\Http\Request::class,
            )->getQuery(name: 'query')));
            $this->filter->setFilter(filter: Value::toArray(Value::instance(
                $request,
                \Laminas\Http\Request::class,
            )->getQuery(name: 'filter', default: [])));
        }

        if (null !== Value::instance($request, \Laminas\Http\Request::class)->getQuery(name: 'facet')) {
            $this->filter->setFacet(facet: Value::array(Value::instance(
                $request,
                \Laminas\Http\Request::class,
            )->getQuery(name: 'facet')));
        }

        if (
            !empty(Value::instance($request, \Laminas\Http\Request::class)->getQuery(
                name: 'dateInterval',
            ))
        ) {
            $dateInterval = DateInterval::fromValue(value: Value::string(Value::instance(
                $request,
                \Laminas\Http\Request::class,
            )->getQuery(name: 'dateInterval')));
            $this->filter->setDateInterval(dateInterval: $dateInterval);
        }

        if (null !== Value::instance($request, \Laminas\Http\Request::class)->getQuery(name: 'order')) {
            $this->filter->setOrder(order: Value::string(Value::instance(
                $request,
                \Laminas\Http\Request::class,
            )->getQuery(name: 'order')));
        }

        if (
            null !== Value::instance($request, \Laminas\Http\Request::class)->getQuery(name: 'direction')
        ) {
            $this->filter->setDirection(direction: Value::string(Value::instance(
                $request,
                \Laminas\Http\Request::class,
            )->getQuery(name: 'direction')));
        }

        // If the form is submitted, refresh the URL
        if (Value::instance($request, \Laminas\Http\Request::class)->getQuery(name: 'reset') !== null) {
            $this->filter = new SearchFormResult(
                order: Value::string(Value::instance(
                    $request,
                    \Laminas\Http\Request::class,
                )->getQuery(name: 'order', default: 'default')),
                direction: Value::string(Value::instance(
                    $request,
                    \Laminas\Http\Request::class,
                )->getQuery(name: 'direction', default: Order::Ascending->value)),
            );
        }

        if (Value::instance($this->request, \Laminas\Http\Request::class)->isPost()) {
            $this->filter = new SearchFormResult(
                order: Value::string(Value::instance(
                    $this->request,
                    \Laminas\Http\Request::class,
                )->getQuery(name: 'order', default: 'default')),
                direction: Value::string(Value::instance(
                    $this->request,
                    \Laminas\Http\Request::class,
                )->getQuery(name: 'direction', default: Order::Ascending->value)),
                query: Value::nullableString(Value::instance(
                    $this->request,
                    \Laminas\Http\Request::class,
                )->getPost(name: 'query')),
                filter: Value::array(Value::instance(
                    $this->request,
                    \Laminas\Http\Request::class,
                )->getPost(name: 'filter', default: [])),
                facet: Value::array(Value::instance(
                    $this->request,
                    \Laminas\Http\Request::class,
                )->getPost(name: 'facet', default: [])),
                dateInterval: Value::instance(
                    Value::instance($this->request, \Laminas\Http\Request::class)->getPost(
                        name: 'dateInterval',
                        default: new DateInterval(),
                    ),
                    \Jield\Search\ValueObject\DateInterval::class,
                ),
            );
        }

        return $this;
    }

    public function getFilter(): SearchFormResult
    {
        return $this->filter;
    }

    #[Pure]
    public function getOrder(): string
    {
        return $this->filter->getOrder();
    }

    #[Pure]
    public function getDirection(): string
    {
        return $this->filter->getDirection();
    }

    #[Pure]
    public function getQuery(): ?string
    {
        return $this->filter->getQuery();
    }

    #[Pure]
    public function getFacet(): array
    {
        return $this->filter->getFacet();
    }

    public function getDateInterval(): DateInterval
    {
        return $this->filter->getDateInterval();
    }

    public function getEncodedFilter(): ?string
    {
        return urlencode(string: $this->filter->getHash());
    }

    public function setFacetByKey(string $facetKey, array|string $value): void
    {
        if (
            Value::instance($this->request, \Laminas\Http\Request::class)->getQuery(name: 'query')
            === null
        ) {
            $this->filter->setFacetByKey(key: $facetKey, value: $value);
        }
    }

    public function setFilterByKey(string $filterKey, array|string|int $value): void
    {
        if (
            Value::instance($this->request, \Laminas\Http\Request::class)->getQuery(name: 'query')
            === null
        ) {
            $this->filter->setFilterByKey(key: $filterKey, value: $value);
        }
    }

    public function parseFilteredSortQuery(array $removeParams = []): string
    {
        $filterCopy = $this->getFilterFormData();
        foreach ($removeParams as $param) {
            unset($filterCopy[$param]);
        }

        return http_build_query(data: ['filter' => $this->getFilterFormData(), 'submit' => 'true']);
    }

    public function getFilterFormData(): array
    {
        return [
            'filter' => $this->filter->getFilter(),
            'facet' => $this->filter->getFacet(),
            'query' => $this->filter->getQuery(),
            'dateInterval' => $this->filter->getDateInterval()->toValue(),
        ];
    }
}
