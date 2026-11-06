<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Metadata;

use Jield\ApiTools\Hal\Exception;
use Laminas\Filter\FilterChain;
use Laminas\Hydrator\ExtractionInterface;
use Laminas\Hydrator\HydratorPluginManager;
use Laminas\Hydrator\HydratorPluginManagerInterface;

use function class_exists;
use function gettype;
use function is_object;
use function is_string;
use function method_exists;
use function sprintf;
use function trigger_error;

use const E_USER_DEPRECATED;

class Metadata
{
    /**
     * Class this metadata applies to
     *
     * @var string
     */
    protected string $class;

    /**
     * Name of the field representing the collection
     *
     * @var string
     */
    protected string $collectionName = 'items';

    /**
     * Hydrator to use when extracting object of this class
     */
    protected ?ExtractionInterface $hydrator = null;

    protected HydratorPluginManagerInterface|HydratorPluginManager $hydrators;

    /**
     * Name of the field representing the identifier
     */
    protected string $entityIdentifierName;

    /**
     * Route for entities composed in a collection
     *
     * @var string
     */
    protected ?string $entityRoute = null;

    /**
     * Name of the route parameter identifier for the entity
     *
     * @var string
     */
    protected string $routeIdentifierName;

    /**
     * Does the class represent a collection?
     *
     * @var bool
     */
    protected bool $isCollection = false;

    /**
     * Collection of additional relational links to inject in entity
     *
     * @var array<array-key,array{
     *     rel: string|array<array-key,string>,
     *     props?: array<array-key,mixed>,
     *     href?: string,
     *     route?: string|array{name:string,params:string|array<array-key,mixed>,options:string|array<array-key,mixed>},
     *     url?: string
     * }>
     */
    protected array $links = [];

    /**
     * Whether to force the existance of a "self" link. The HAl specification encourages it but it is not strictly
     * required.
     *
     * @var bool
     */
    protected bool $forceSelfLink = true;

    /**
     * Route to use to generate a static link for this entity
     *
     * @var string
     */
    protected ?string $route = null;

    /**
     * Additional options to use when generating a static link for this entity
     *
     * @var array
     */
    protected array $routeOptions = [];

    /**
     * Additional route parameters to use when generating a static link for this entity
     *
     * @var array<string,mixed>
     */
    protected array $routeParams = [];

    /**
     * URL to use for this entity (instead of a route)
     *
     * @var string
     */
    protected ?string $url = null;

    /**
     * Maximum number of nesting levels
     *
     * @var int
     */
    protected int $maxDepth;

    /**
     * Constructor
     *
     * Sets the class, and passes any options provided to the appropriate
     * setter methods, after first converting them to lowercase and stripping
     * underscores.
     *
     * If the class does not exist, raises an exception.
     */
    public function __construct(string $class, array $options = [], ?HydratorPluginManagerInterface $hydrators = null)
    {

        if (!class_exists(class: $class)) {
            throw new Exception\InvalidArgumentException(message: sprintf(
                'Class provided to %s must exist; received "%s"',
                static::class,
                $class,
            ));
        }

        $this->class = $class;

        if ($hydrators instanceof \Laminas\Hydrator\HydratorPluginManagerInterface) {
            $this->setHydrators(hydrators: $hydrators);
        }

        /** @var string|bool $legacyIdentifierName */
        $legacyIdentifierName = false;

        /** @var mixed $value */
        foreach ($options as $key => $value) {
            /** @var string $filteredKey */
            $filteredKey = strtolower(str_replace('_', '', (string) $key));

            if ($filteredKey === 'class') {
                continue;
            }

            // Strip "name" from route_name key
            // Rename "resourceroutename" and "resourceroute" to "entityroute".
            // Don't generically strip all 'name's
            if ($filteredKey === 'routename') {
                $filteredKey = 'route';
            }

            if ($filteredKey === 'resourceroutename' || $filteredKey === 'resourceroute') {
                $filteredKey = 'entityroute';
            }

            if ($filteredKey === 'entityroutename') {
                $filteredKey = 'entityroute';
            }

            // Fix BC issue: s/identifier_name/route_identifier_name/
            if ($filteredKey === 'identifiername') {
                /** @var mixed $legacyIdentifierName */
                $legacyIdentifierName = $value;
                continue;
            }

            $method = 'set' . $filteredKey;
            if (method_exists(object_or_class: $this, method: $method)) {
                \Jield\Stdlib\Value::call($this, $method, [$value], scope: self::class);
            } else {
                throw new Exception\InvalidArgumentException(message: sprintf(
                    'Unhandled option passed to Metadata constructor: %s %s',
                    $method,
                    $key,
                ));
            }
        }

        if (is_string(value: $legacyIdentifierName)) {
            if (!$this->routeIdentifierName) {
                $this->setRouteIdentifierName(identifier: $legacyIdentifierName);
            }

            if (!$this->entityIdentifierName) {
                $this->setEntityIdentifierName(identifier: $legacyIdentifierName);
            }
        }
    }

    /**
     * Retrieve the class this metadata is associated with
     *
     * @return string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * Retrieve the collection name
     *
     * @return string
     */
    public function getCollectionName(): string
    {
        return $this->collectionName;
    }

    /**
     * Retrieve the hydrator to associate with this class, if any
     *
     * @return null|ExtractionInterface
     */
    public function getHydrator(): ?ExtractionInterface
    {
        return $this->hydrator;
    }

    /**
     * Retrieve the entity identifier name
     *
     * @return string
     */
    public function getEntityIdentifierName(): string
    {
        return $this->entityIdentifierName;
    }

    /**
     * Retrieve the route identifier name
     *
     * @return string
     */
    public function getRouteIdentifierName(): string
    {
        return $this->routeIdentifierName;
    }

    /**
     * Retrieve set of relational links to inject, if any
     *
     * @return array<array-key,array{
     *     rel: string|array<array-key,string>,
     *     props?: array<array-key,mixed>,
     *     href?: string,
     *     route?: string|array{name:string,params:string|array<array-key,mixed>,options:string|array<array-key,mixed>},
     *     url?: string
     * }>
     */
    public function getLinks(): array
    {
        return $this->links;
    }

    /**
     * Retrieve the entity route
     *
     * If not set, uses the route or url, depending on which is present.
     *
     * @return null|string
     */
    public function getEntityRoute(): ?string
    {
        if (null === $this->entityRoute) {
            if ($this->hasRoute()) {
                $this->setEntityRoute(route: \Jield\Stdlib\Value::string($this->getRoute()));
            } else {
                $this->setEntityRoute(route: \Jield\Stdlib\Value::string($this->getUrl()));
            }
        }

        return $this->entityRoute;
    }

    /**
     * Retrieve the route to use for URL generation
     *
     * @return null|string
     */
    public function getRoute(): ?string
    {
        return $this->route;
    }

    /**
     * Retrieve an route options to use in URL generation
     *
     * @return array
     */
    public function getRouteOptions(): array
    {
        return $this->routeOptions;
    }

    /**
     * Retrieve any route parameters to use in URL generation
     *
     * @return array<string,mixed>
     */
    public function getRouteParams(): array
    {
        return $this->routeParams;
    }

    /**
     * Retrieve the URL to use for this entity, if present
     *
     * @return null|string
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * Retrieve the maximum number of nesting levels
     *
     * @return int
     */
    public function getMaxDepth(): int
    {
        return $this->maxDepth;
    }

    /**
     * Is a hydrator associated with this class?
     *
     * @return bool
     */
    public function hasHydrator(): bool
    {
        return null !== $this->hydrator;
    }

    /**
     * Is a route present for this class?
     *
     * @return bool
     */
    public function hasRoute(): bool
    {
        return null !== $this->route;
    }

    /**
     * Is a URL set for this class?
     *
     * @return bool
     */
    public function hasUrl(): bool
    {
        return null !== $this->url;
    }

    /**
     * Does this class represent a collection?
     *
     * @return bool
     */
    public function isCollection(): bool
    {
        return $this->isCollection;
    }

    /**
     * Set the collection name
     *
     * @param string $collectionName
     * @return static
     */
    public function setCollectionName(string $collectionName): static
    {
        $this->collectionName = $collectionName;
        return $this;
    }

    /**
     * Set the hydrator to use with this class
     *
     * @param string|ExtractionInterface $hydrator
     * @return static
     * @throws Exception\InvalidArgumentException If the class or hydrator does not implement ExtractionInterface.
     */
    public function setHydrator(ExtractionInterface|string $hydrator): static
    {
        if (is_string(value: $hydrator)) {
            if ($this->hydrators->has($hydrator)) {
                /** @var mixed $hydrator */
                $hydrator = $this->hydrators->get($hydrator);
            } elseif (class_exists(class: $hydrator)) {
                /** @var ExtractionInterface $hydrator */
                $hydrator = \Jield\Stdlib\Value::instantiate($hydrator);
            }
        }

        if (!$hydrator instanceof ExtractionInterface) {
            if (is_object(value: $hydrator)) {
                $type = $hydrator::class;
            } elseif (is_string(value: $hydrator)) {
                $type = $hydrator;
            } else {
                $type = gettype(value: $hydrator);
            }

            throw new Exception\InvalidArgumentException(message: sprintf(
                'Hydrator class must implement Laminas\Hydrator\ExtractionInterface; received "%s"',
                $type,
            ));
        }

        $this->hydrator = $hydrator;
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
     * Set the flag indicating collection status
     *
     * @param bool $flag
     * @return static
     */
    public function setIsCollection(bool $flag): static
    {
        $this->isCollection = $flag;
        return $this;
    }

    /**
     * Set relational links.
     *
     * Each element in the array should be an array with the elements:
     *
     * - rel - the link relation
     * - url - the URL to use for the link (deprecated since 1.5.0; use "href" instead) OR
     * - href - the href to use for the link OR
     * - route - an array of route information for generating the link; this
     *   should include the elements "name" (required; the route name),
     *   "params" (optional; additional parameters to inject), and "options"
     *   (optional; additional options to pass to the router for assembly)
     *
     * @psalm-param array<array-key,array{
     *     rel: string|array<array-key,string>,
     *     props?: array<array-key,mixed>,
     *     href?: string,
     *     route?: string|array{name:string,params:string|array<array-key,mixed>,options:string|array<array-key,mixed>},
     *     url?: string
     * }> $links
     */
    public function setLinks(array $links): static
    {
        $this->links = $links;
        return $this;
    }

    /**
     * Set the entity route (for embedded entities in collections)
     *
     * @param string $route
     * @return static
     */
    public function setEntityRoute(string $route): static
    {
        $this->entityRoute = $route;
        return $this;
    }

    /**
     * Set the entity route (for embedded entities in collections)
     *
     * Deprecated; please use setEntityRoute().
     *
     * @param string $route
     * @return static
     * @deprecated
     *
     */
    public function setResourceRoute(string $route): static
    {
        trigger_error(
            message: sprintf('%s is deprecated; please use %s::setEntityRoute', __METHOD__, static::class),
            error_level: E_USER_DEPRECATED,
        );
        return $this->setEntityRoute(route: $route);
    }

    /**
     * Set the route for URL generation
     *
     * @param string $route
     * @return static
     */
    public function setRoute(string $route): static
    {
        $this->route = $route;
        return $this;
    }

    /**
     * Set route options for URL generation
     *
     * @param array $options
     * @return static
     */
    public function setRouteOptions(array $options): static
    {
        $this->routeOptions = $options;
        return $this;
    }

    /**
     * Set route parameters for URL generation
     *
     * @param array<string,mixed> $params
     * @return static
     */
    public function setRouteParams(array $params): static
    {
        $this->routeParams = $params;
        return $this;
    }

    /**
     * Set the URL to use with this entity
     *
     * @param string $url
     * @return static
     */
    public function setUrl(string $url): static
    {
        $this->url = $url;
        return $this;
    }

    /**
     * Set the maximum number of nesting levels
     *
     * @param int $maxDepth
     * @return static
     */
    public function setMaxDepth(int $maxDepth): static
    {
        $this->maxDepth = $maxDepth;
        return $this;
    }

    /**
     * Returns true if this entity should be forced to have a "self" link.
     *
     * @return bool
     */
    public function getForceSelfLink(): bool
    {
        return $this->forceSelfLink;
    }

    /**
     * Set whether to force the existance of "self" links.
     *
     * @param bool $forceSelfLink A truthy value
     * @return static
     */
    public function setForceSelfLink(bool $forceSelfLink): static
    {
        $this->forceSelfLink = $forceSelfLink;
        return $this;
    }

    /**
     * @param HydratorPluginManager|HydratorPluginManagerInterface $hydrators
     * @throws Exception\InvalidArgumentException If $hydrators is an invaild type.
     */
    private function setHydrators(HydratorPluginManager|HydratorPluginManagerInterface $hydrators): void
    {
        $this->hydrators = $hydrators;
    }
}
