<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal;

use Exception;
use Jield\ApiTools\Hal\Exception\InvalidArgumentException;
use Laminas\Paginator\Paginator;
use Laminas\Stdlib\ArrayUtils;
use Traversable;
use function sprintf;

/**
 * Model a collection for use with HAL payloads
 */
class Collection implements Link\LinkCollectionAwareInterface
{
    use Link\LinkCollectionAwareTrait;

    /**
     * Additional attributes to render with the collection
     */
    protected array $attributes = [];

    protected Paginator|iterable $collection;

    /**
     * Name of a collection (used to identify it in the "_embedded" object)
     */
    protected string $collectionName = 'items';

    protected string $collectionRoute;

    protected array $collectionRouteOptions = [];

    protected array $collectionRouteParams = [];

    /**
     * Name of the field representing the identifier
     */
    protected string $entityIdentifierName = 'id';

    /**
     * Name of the route parameter identifier for individual entities of the collection
     */
    protected string $routeIdentifierName = 'id';

    /**
     * Current page
     */
    protected int $page = 1;

    /**
     * Number of entities per page
     *
     * @var int
     */
    protected int $pageSize = 30;

    protected string $entityRoute;

    protected array $entityRouteOptions = [];

    protected array $entityRouteParams = [];

    public function __construct(
        Paginator|iterable $collection,
        ?string            $entityRoute = null,
        ?iterable          $entityRouteParams = null,
        ?iterable          $entityRouteOptions = null
    )
    {
        /** @psalm-suppress PossiblyInvalidPropertyAssignmentValue */
        $this->collection = $collection;

        if (null !== $entityRoute) {
            $this->setEntityRoute(route: $entityRoute);
        }

        if (null !== $entityRouteParams) {
            $this->setEntityRouteParams(params: $entityRouteParams);
        }

        if (null !== $entityRouteOptions) {
            $this->setEntityRouteOptions(options: $entityRouteOptions);
        }
    }

    /**
     * Proxy to properties to allow read access
     *
     * @param string $name
     * @return mixed
     * @throws Exception
     */
    public function __get(string $name)
    {
        throw new Exception(message: 'Direct query of values is deprecated.  Use getters.');
    }

    /**
     * Set additional attributes to render as part of the collection
     *
     * @param array $attributes
     * @return static
     */
    public function setAttributes(array $attributes): static
    {
        $this->attributes = $attributes;
        return $this;
    }

    /**
     * Set the collection name (for use within the _embedded object)
     *
     * @param string $name
     * @return static
     */
    public function setCollectionName(string $name): static
    {
        $this->collectionName = $name;
        return $this;
    }

    /**
     * Set the collection route; used for generating pagination links
     *
     * @param string $route
     * @return static
     */
    public function setCollectionRoute(string $route): static
    {
        $this->collectionRoute = $route;
        return $this;
    }

    /**
     * Set options to use with the collection route; used for generating pagination links
     *
     * @param Traversable|array $options
     * @return static
     * @throws InvalidArgumentException
     */
    public function setCollectionRouteOptions(iterable $options): static
    {
        $options = ArrayUtils::iteratorToArray(iterator: $options);

        $this->collectionRouteOptions = $options;
        return $this;
    }

    /**
     * Set the route identifier name
     *
     * @param string $identifier
     * @return static
     */
    public function setRouteIdentifierName(string $identifier): static
    {
        $this->routeIdentifierName = $identifier;
        return $this;
    }

    /**
     * Set the entity identifier name
     *
     * @param string $identifier
     * @return static
     */
    public function setEntityIdentifierName(string $identifier): static
    {
        $this->entityIdentifierName = $identifier;
        return $this;
    }

    /**
     * Set current page
     *
     * @param int $page
     * @return static
     * @throws InvalidArgumentException For non-positive and/or non-integer values.
     */
    public function setPage(int $page): static
    {
        if ($page < 1) {
            throw new InvalidArgumentException(message: sprintf(
                'Page must be a positive integer; received "%s"',
                $page
            ));
        }

        $this->page = $page;
        return $this;
    }

    /**
     * Set page size
     *
     * @param int $size
     * @return static
     * @throws InvalidArgumentException For non-positive and/or non-integer values.
     */
    public function setPageSize(int $size): static
    {
        if ($size < 1 && $size !== -1) {
            throw new InvalidArgumentException(message: sprintf(
                'size must be a positive integer or -1 (to disable pagination); received "%s"',
                $size
            ));
        }

        $this->pageSize = $size;
        return $this;
    }

    /**
     * Set the entity route
     */
    public function setEntityRoute(string $route): static
    {
        $this->entityRoute = $route;
        return $this;
    }

    public function setEntityRouteOptions(iterable $options): static
    {
        $options = ArrayUtils::iteratorToArray(iterator: $options);

        $this->entityRouteOptions = $options;
        return $this;
    }

    public function setEntityRouteParams(iterable $params): static
    {
        $params = ArrayUtils::iteratorToArray(iterator: $params);

        $this->entityRouteParams = $params;
        return $this;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getCollection(): Paginator|iterable
    {
        return $this->collection;
    }

    public function getCollectionName(): string
    {
        return $this->collectionName;
    }

    public function getCollectionRoute(): string
    {
        return $this->collectionRoute;
    }

    public function getCollectionRouteOptions(): array
    {
        return $this->collectionRouteOptions;
    }

    public function getCollectionRouteParams(): array
    {
        return $this->collectionRouteParams;
    }

    public function getRouteIdentifierName(): string
    {
        return $this->routeIdentifierName;
    }

    public function getEntityIdentifierName(): string
    {
        return $this->entityIdentifierName;
    }

    public function getEntityRoute(): string
    {
        return $this->entityRoute;
    }

    public function getEntityRouteOptions(): array
    {
        return $this->entityRouteOptions;
    }

    public function getEntityRouteParams(): array
    {
        return $this->entityRouteParams;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPageSize(): int
    {
        return $this->pageSize;
    }
}
