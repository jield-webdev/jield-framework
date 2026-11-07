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
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

use function http_build_query;
use function urldecode;
use function urlencode;

class GetFilter extends AbstractPlugin
{
    private SearchFormResult $filter;

    protected ?string $encodedFilter = null;

    private readonly Request $request;

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __construct(
        private readonly ContainerInterface $container,
    ) {
        /** @var Application $application */
        $application = $this->container->get('application');

        $this->encodedFilter = urldecode(
            string: (string) $application->getMvcEvent()->getRouteMatch()?->getParam(name: 'encodedFilter'),
        );

        $request = $application->getMvcEvent()->getRequest();
        Assert::isInstanceOf($request, Request::class);
        $this->request = $request;
    }

    public function __invoke(): GetFilter
    {
        //Make local variables
        $request = $this->request;
        $encodedFilter = $this->encodedFilter;

        //Initiate the filter
        $this->filter = new SearchFormResult(
            order: Assert::string($request->getQuery(name: 'order', default: 'default')),
            direction: Assert::string($request->getQuery(name: 'direction', default: Order::Ascending->value)),
        );

        if (!empty($encodedFilter)) {
            $this->filter->updateFromEncodedFilter(encodedFilter: $encodedFilter);
        }

        // If the form is submitted, refresh the URL
        if ($request->getQuery(name: 'query') !== null) {
            $this->filter->setQuery(query: Assert::nullOrString($request->getQuery(name: 'query')));
            $this->filter->setFilter(filter: Value::toArray($request->getQuery(name: 'filter', default: [])));
        }

        if (null !== $request->getQuery(name: 'facet')) {
            $this->filter->setFacet(facet: Assert::isArray($request->getQuery(name: 'facet')));
        }

        if (!empty($request->getQuery(name: 'dateInterval'))) {
            $dateInterval = DateInterval::fromValue(value: Assert::string($request->getQuery(name: 'dateInterval')));
            $this->filter->setDateInterval(dateInterval: $dateInterval);
        }

        if (null !== $request->getQuery(name: 'order')) {
            $this->filter->setOrder(order: Assert::string($request->getQuery(name: 'order')));
        }

        if (null !== $request->getQuery(name: 'direction')) {
            $this->filter->setDirection(direction: Assert::string($request->getQuery(name: 'direction')));
        }

        // If the form is submitted, refresh the URL
        if ($request->getQuery(name: 'reset') !== null) {
            $this->filter = new SearchFormResult(
                order: Assert::string($request->getQuery(name: 'order', default: 'default')),
                direction: Assert::string($request->getQuery(name: 'direction', default: Order::Ascending->value)),
            );
        }

        if ($this->request->isPost()) {
            $this->filter = new SearchFormResult(
                order: Assert::string($this->request->getQuery(name: 'order', default: 'default')),
                direction: Assert::string($this->request->getQuery(name: 'direction', default: Order::Ascending->value)),
                query: Assert::nullOrString($this->request->getPost(name: 'query')),
                filter: Assert::isArray($this->request->getPost(name: 'filter', default: [])),
                facet: Assert::isArray($this->request->getPost(name: 'facet', default: [])),
                dateInterval: Assert::isInstanceOf($this->request->getPost(name: 'dateInterval', default: new DateInterval()), \Jield\Search\ValueObject\DateInterval::class),
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
        if ($this->request->getQuery(name: 'query') === null) {
            $this->filter->setFacetByKey(key: $facetKey, value: $value);
        }
    }

    public function setFilterByKey(string $filterKey, array|string|int $value): void
    {
        if ($this->request->getQuery(name: 'query') === null) {
            $this->filter->setFilterByKey(key: $filterKey, value: $value);
        }
    }

    public function parseFilteredSortQuery(array $removeParams = []): string
    {
        $filterCopy = $this->getFilterFormData();
        /** @mago-var mixed $param */
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
