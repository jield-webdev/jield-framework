<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Plugin;

use ArrayObject;
use Jield\ApiTools\ApiProblem\ApiProblem;
use Jield\ApiTools\Hal\Collection;
use Jield\ApiTools\Hal\Entity;
use Jield\ApiTools\Hal\EntityHydratorManager;
use Jield\ApiTools\Hal\Exception;
use Jield\ApiTools\Hal\Extractor\EntityExtractor;
use Jield\ApiTools\Hal\Extractor\LinkCollectionExtractorInterface;
use Jield\ApiTools\Hal\Link\Link;
use Jield\ApiTools\Hal\Link\LinkCollection;
use Jield\ApiTools\Hal\Link\LinkCollectionAwareInterface;
use Jield\ApiTools\Hal\Link\LinkUrlBuilder;
use Jield\ApiTools\Hal\Link\PaginationInjector;
use Jield\ApiTools\Hal\Link\PaginationInjectorInterface;
use Jield\ApiTools\Hal\Link\SelfLinkInjector;
use Jield\ApiTools\Hal\Link\SelfLinkInjectorInterface;
use Jield\ApiTools\Hal\Metadata\MetadataMap;
use Jield\ApiTools\Hal\ResourceFactory;
use Jield\Mvc\Controller\Plugin\PluginInterface as ControllerPluginInterface;
use Jield\Mvc\View\Helper\AbstractHelper;
use Laminas\EventManager\Event;
use Laminas\EventManager\EventInterface;
use Laminas\EventManager\EventManagerAwareInterface;
use Laminas\EventManager\EventManagerAwareTrait;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Hydrator\ExtractionInterface;
use Laminas\Hydrator\HydratorPluginManager;
use Laminas\Paginator\Paginator;
use Laminas\Stdlib\DispatchableInterface;
use Override;

use function array_key_exists;
use function array_merge;
use function count;
use function intval;
use function is_array;
use function is_object;
use function method_exists;
use function spl_object_hash;
use function sprintf;
use function trigger_error;

use const E_USER_DEPRECATED;

/**
 * Generate links for use with HAL payloads
 */
class Hal extends AbstractHelper implements ControllerPluginInterface, EventManagerAwareInterface
{
    use EventManagerAwareTrait;

    protected ?DispatchableInterface $controller = null;

    protected ?ResourceFactory $resourceFactory = null;

    protected ?EntityHydratorManager $entityHydratorManager = null;

    protected ?EntityExtractor $entityExtractor = null;

    /**
     * Boolean to render embedded entities or just include _embedded data
     *
     * @var bool
     */
    protected bool $renderEmbeddedEntities = true;

    /**
     * Boolean to render collections or just return their _embedded data
     *
     * @var bool
     */
    protected bool $renderCollections = true;

    protected ?MetadataMap $metadataMap = null;

    protected ?PaginationInjectorInterface $paginationInjector = null;

    protected ?SelfLinkInjectorInterface $selfLinkInjector = null;

    protected ?LinkUrlBuilder $linkUrlBuilder = null;

    protected ?LinkCollectionExtractorInterface $linkCollectionExtractor = null;

    /**
     * Entities spl hash stack for circular reference detection
     *
     * @var array
     */
    protected array $entityHashStack = [];

    public function __construct(
        protected HydratorPluginManager $hydrators,
    ) {
        $this->hydrators = $hydrators;
    }

    #[Override]
    public function setController(DispatchableInterface $controller): void
    {
        $this->controller = $controller;
    }

    /**
     * @return DispatchableInterface
     */
    #[Override]
    public function getController(): DispatchableInterface
    {
        return \Jield\Stdlib\Value::instance($this->controller, \Laminas\Stdlib\DispatchableInterface::class);
    }

    /**
     * Set the event manager instance
     *
     * @psalm-suppress ParamNameMismatch
     */
    #[Override]
    public function setEventManager(EventManagerInterface $eventManager): static
    {
        $eventManager->setIdentifiers(identifiers: [
            static::class,
            static::class,
        ]);

        $this->events = $eventManager;

        $eventManager->attach(eventName: 'getIdFromEntity', listener: static function (EventInterface $e) {
            /** @var mixed $entity */
            $entity = $e->getParam(name: 'entity');

            // Found id in array
            if (is_array(value: $entity) && array_key_exists(key: 'id', array: $entity)) {
                return $entity['id'];
            }

            // No id in array, or not an object; return false
            if (!is_object(value: $entity)) {
                return false;
            }

            // Found public id property on object
            if (isset($entity->id)) {
                return $entity->id;
            }

            // Found public id getter on object
            if (method_exists(object_or_class: $entity, method: 'getid')) {
                /** @psalm-var Entity $entity */
                return $entity->getId();
            }

            // not found
            return false;
        });

        return $this;
    }

    /**
     * @return ResourceFactory
     */
    public function getResourceFactory(): ResourceFactory
    {
        if (!$this->resourceFactory instanceof ResourceFactory) {
            $this->resourceFactory = new ResourceFactory(
                entityHydratorManager: $this->getEntityHydratorManager(),
                entityExtractor: $this->getEntityExtractor(),
            );
        }

        return $this->resourceFactory;
    }

    public function setResourceFactory(ResourceFactory $factory): static
    {
        $this->resourceFactory = $factory;
        return $this;
    }

    /**
     * @return EntityHydratorManager
     */
    public function getEntityHydratorManager(): EntityHydratorManager
    {
        if (!$this->entityHydratorManager instanceof EntityHydratorManager) {
            $this->entityHydratorManager = new EntityHydratorManager(
                hydrators: $this->hydrators,
                map: $this->getMetadataMap(),
            );
        }

        return $this->entityHydratorManager;
    }

    public function setEntityHydratorManager(EntityHydratorManager $manager): static
    {
        $this->entityHydratorManager = $manager;
        return $this;
    }

    /**
     * @return EntityExtractor
     */
    public function getEntityExtractor(): EntityExtractor
    {
        if (!$this->entityExtractor instanceof EntityExtractor) {
            $this->entityExtractor = new EntityExtractor(entityHydratorManager: $this->getEntityHydratorManager());
        }

        return $this->entityExtractor;
    }

    public function setEntityExtractor(EntityExtractor $extractor): static
    {
        $this->entityExtractor = $extractor;
        return $this;
    }

    /**
     * @return HydratorPluginManager
     */
    public function getHydratorManager(): HydratorPluginManager
    {
        return $this->hydrators;
    }

    /**
     * @return MetadataMap
     */
    public function getMetadataMap(): MetadataMap
    {
        if (!$this->metadataMap instanceof MetadataMap) {
            $this->setMetadataMap(map: new MetadataMap());
        }

        return $this->metadataMap;
    }

    public function setMetadataMap(MetadataMap $map): static
    {
        $this->metadataMap = $map;
        return $this;
    }

    public function setLinkUrlBuilder(LinkUrlBuilder $builder): static
    {
        $this->linkUrlBuilder = $builder;
        return $this;
    }

    /**
     * @return never
     * @throws Exception\DeprecatedMethodException
     *
     * @deprecated Since 1.4.0; use setLinkUrlBuilder() instead.
     *
     */
    public function setServerUrlHelper(callable $helper): never
    {
        throw new Exception\DeprecatedMethodException(message: sprintf(
            '%s can no longer be used to influence URL generation; please '
            . 'use %s::setLinkUrlBuilder() instead, providing a configured '
            . '%s instance',
            __METHOD__,
            static::class,
            LinkUrlBuilder::class,
        ));
    }

    /**
     * @return never
     * @throws Exception\DeprecatedMethodException
     *
     * @deprecated Since 1.4.0; use setLinkUrlBuilder() instead.
     *
     */
    public function setUrlHelper(callable $helper): never
    {
        throw new Exception\DeprecatedMethodException(message: sprintf(
            '%s can no longer be used to influence URL generation; please '
            . 'use %s::setLinkUrlBuilder() instead, providing a configured '
            . '%s instance',
            __METHOD__,
            static::class,
            LinkUrlBuilder::class,
        ));
    }

    /**
     * @return PaginationInjectorInterface
     */
    public function getPaginationInjector(): PaginationInjectorInterface
    {
        if (!$this->paginationInjector instanceof PaginationInjectorInterface) {
            $this->setPaginationInjector(injector: new PaginationInjector());
        }

        return $this->paginationInjector;
    }

    public function setPaginationInjector(PaginationInjectorInterface $injector): static
    {
        $this->paginationInjector = $injector;
        return $this;
    }

    /**
     * @return SelfLinkInjectorInterface
     */
    public function getSelfLinkInjector(): SelfLinkInjectorInterface
    {
        if (!$this->selfLinkInjector instanceof SelfLinkInjectorInterface) {
            $this->setSelfLinkInjector(injector: new SelfLinkInjector());
        }

        return $this->selfLinkInjector;
    }

    public function setSelfLinkInjector(SelfLinkInjectorInterface $injector): static
    {
        $this->selfLinkInjector = $injector;
        return $this;
    }

    /**
     * @return LinkCollectionExtractorInterface
     */
    public function getLinkCollectionExtractor(): LinkCollectionExtractorInterface
    {
        return \Jield\Stdlib\Value::instance($this->linkCollectionExtractor, \Jield\ApiTools\Hal\Extractor\LinkCollectionExtractorInterface::class);
    }

    public function setLinkCollectionExtractor(LinkCollectionExtractorInterface $extractor): static
    {
        $this->linkCollectionExtractor = $extractor;
        return $this;
    }

    /**
     * Map an entity class to a specific hydrator instance
     *
     * @param string $class
     * @param ExtractionInterface $hydrator
     * @return static
     */
    public function addHydrator(string $class, ExtractionInterface $hydrator): static
    {
        $this->getEntityHydratorManager()->addHydrator(class: $class, hydrator: $hydrator);
        return $this;
    }

    /**
     * Set the default hydrator to use if none specified for a class.
     *
     */
    public function setDefaultHydrator(ExtractionInterface $hydrator): static
    {
        $this->getEntityHydratorManager()->setDefaultHydrator(hydrator: $hydrator);
        return $this;
    }

    /**
     * Set boolean to render embedded entities or just include _embedded data
     *
     * @param bool $value
     * @return static
     * @deprecated
     *
     */
    public function setRenderEmbeddedResources(bool $value): static
    {
        trigger_error(
            message: sprintf(
                '%s has been deprecated; please use %s::setRenderEmbeddedEntities',
                __METHOD__,
                static::class,
            ),
            error_level: E_USER_DEPRECATED,
        );
        $this->renderEmbeddedEntities = $value;
        return $this;
    }

    /**
     * Set boolean to render embedded entities or just include _embedded data
     *
     * @param bool $value
     * @return static
     */
    public function setRenderEmbeddedEntities(bool $value): static
    {
        $this->renderEmbeddedEntities = $value;
        return $this;
    }

    /**
     * Get boolean to render embedded resources or just include _embedded data
     *
     * @return bool
     * @deprecated
     *
     */
    public function getRenderEmbeddedResources(): bool
    {
        trigger_error(
            message: sprintf(
                '%s has been deprecated; please use %s::getRenderEmbeddedEntities',
                __METHOD__,
                static::class,
            ),
            error_level: E_USER_DEPRECATED,
        );
        return $this->renderEmbeddedEntities;
    }

    /**
     * Get boolean to render embedded entities or just include _embedded data
     *
     * @return bool
     */
    public function getRenderEmbeddedEntities(): bool
    {
        return $this->renderEmbeddedEntities;
    }

    /**
     * Set boolean to render embedded collections or just include _embedded data
     *
     * @param bool $value
     * @return static
     */
    public function setRenderCollections(bool $value): static
    {
        $this->renderCollections = $value;
        return $this;
    }

    /**
     * Get boolean to render embedded collections or just include _embedded data
     *
     * @return bool
     */
    public function getRenderCollections(): bool
    {
        return $this->renderCollections;
    }

    /**
     * Retrieve a hydrator for a given entity
     *
     * Please use getHydratorForEntity().
     *
     * @param object $resource
     * @return ExtractionInterface|false
     * @deprecated
     *
     */
    public function getHydratorForResource(object $resource): false|ExtractionInterface
    {
        trigger_error(
            message: sprintf('%s is deprecated; please use %s::getHydratorForEntity', __METHOD__, static::class),
            error_level: E_USER_DEPRECATED,
        );
        return static::getHydratorForEntity(entity: $resource);
    }

    /**
     * Retrieve a hydrator for a given entity
     *
     * If the entity has a mapped hydrator, returns that hydrator. If not, and
     * a default hydrator is present, the default hydrator is returned.
     * Otherwise, a boolean false is returned.
     *
     * @param object $entity
     * @return ExtractionInterface|false
     */
    public function getHydratorForEntity(object $entity): false|ExtractionInterface
    {
        return $this->getEntityHydratorManager()->getHydratorForEntity(entity: $entity);
    }

    /**
     * "Render" a Collection
     *
     * Injects pagination links, if the composed collection is a Paginator, and
     * then loops through the collection to create the data structure representing
     * the collection.
     *
     * For each entity in the collection, the event "renderCollection.entity" is
     * triggered, with the following parameters:
     *
     * - "collection", which is the $halCollection passed to the method
     * - "entity", which is the current entity
     * - "route", the resource route that will be used to generate links
     * - "routeParams", any default routing parameters/substitutions to use in URL assembly
     * - "routeOptions", any default routing options to use in URL assembly
     *
     * This event can be useful particularly when you have multi-segment routes
     * and wish to ensure that route parameters are injected, or if you want to
     * inject query or fragment parameters.
     *
     * Event parameters are aggregated in an ArrayObject, which allows you to
     * directly manipulate them in your listeners:
     *
     * <code>
     * $params = $e->getParams();
     * $params['routeOptions']['query'] = ['format' => 'json'];
     * </code>
     *
     * @return array|ApiProblem Associative array representing the payload to render;
     *     returns ApiProblem if error in pagination occurs
     */
    public function renderCollection(Collection $halCollection): array|ApiProblem
    {
        $this->getEventManager()->trigger(eventName: __FUNCTION__, target: $this, argv: [
            'collection' => $halCollection,
        ]);
        $collection = $halCollection->getCollection();
        $collectionName = $halCollection->getCollectionName();

        if ($collection instanceof Paginator) {
            $status = $this->injectPaginationLinks(halCollection: $halCollection);
            if ($status instanceof ApiProblem) {
                return $status;
            }
        }

        $metadataMap = $this->getMetadataMap();

        /** @psalm-suppress PossiblyFalseReference */
        $maxDepth = is_object(value: $collection) && $metadataMap->has(class: $collection)
            ? \Jield\Stdlib\Value::instance($metadataMap->get(class: $collection), \Jield\ApiTools\Hal\Metadata\Metadata::class)->getMaxDepth()
            : null;

        /** @var array<string,mixed> $payload */
        $payload = $halCollection->getAttributes();
        $payload['_links'] = $this->fromResource(resource: $halCollection);
        $payload['_embedded'] = [
            $collectionName => $this->extractCollection(halCollection: $halCollection, depth: 0, maxDepth: $maxDepth),
        ];

        if ($collection instanceof Paginator) {
            $payload['page_count'] = intval(value: $payload['page_count'] ?? $collection->count());
            $payload['page_size'] = intval(value: $payload['page_size'] ?? $halCollection->getPageSize());
            $payload['total_items'] = intval(value: $payload['total_items'] ?? $collection->getTotalItemCount());
            $payload['page'] = $payload['page_count'] > 0 ? $halCollection->getPage() : 0;
        } elseif (is_countable(value: $collection)) {
            $payload['total_items'] = intval(value: $payload['total_items'] ?? count(value: $collection));
        }

        $payload = new ArrayObject( $payload);
        $this->getEventManager()->trigger(eventName: __FUNCTION__ . '.post', target: $this, argv: [
            'payload' => $payload,
            'collection' => $halCollection,
        ]);

        return \Jield\Stdlib\Value::toArray($payload);
    }

    /**
     * Render an individual entity
     *
     * Creates a hash representation of the Entity. The entity is first
     * converted to an array, and its associated links are injected as the
     * "_links" member. If any members of the entity are themselves
     * Entity objects, they are extracted into an "_embedded" hash.
     *
     * @param bool $renderEntity
     * @param int $depth depth of the current rendering recursion
     * @param int|null $maxDepth maximum rendering depth for the current metadata
     * @throws Exception\CircularReferenceException
     */
    public function renderEntity(
        Entity $halEntity,
        bool $renderEntity = true,
        int $depth = 0,
        ?int $maxDepth = null,
    ): array {
        $this->getEventManager()->trigger(eventName: __FUNCTION__, target: $this, argv: ['entity' => $halEntity]);
        $entity = $halEntity->getEntity();
        $entityLinks = clone $halEntity->getLinks(); // Clone to prevent link duplication

        $metadataMap = $this->getMetadataMap();

        if (is_object(value: $entity)) {
            if ($maxDepth === null && $metadataMap->has(class: $entity)) {
                /** @psalm-suppress PossiblyFalseReference */
                $maxDepth = \Jield\Stdlib\Value::instance($metadataMap->get(class: $entity), \Jield\ApiTools\Hal\Metadata\Metadata::class)->getMaxDepth();
            }

            if ($maxDepth === null) {
                $entityHash = spl_object_hash(object: $entity);

                if (isset($this->entityHashStack[$entityHash])) {
                    // we need to clear the stack, as the exception may be caught and the plugin may be invoked again
                    $this->entityHashStack = [];
                    throw new Exception\CircularReferenceException(message: sprintf(
                        "Circular reference detected in '%s'. %s",
                        $entity::class,
                        "Either set a 'max_depth' metadata attribute or remove the reference",
                    ));
                }

                $this->entityHashStack[$entityHash] = $entity::class;
            }
        }

        if (!$renderEntity || $maxDepth !== null && $depth > $maxDepth) {
            $entity = [];
        }

        if (!is_array(value: $entity)) {
            $entity = $this->getEntityExtractor()->extract(object: $entity);
        }

        /** @var mixed $value */
        foreach ($entity as $key => $value) {
            if (is_object(value: $value) && $metadataMap->has(class: $value)) {
                /** @psalm-suppress PossiblyFalseArgument,ArgumentTypeCoercion */
                $value = $this->getResourceFactory()->createEntityFromMetadata(
                    object: $value,
                    metadata: \Jield\Stdlib\Value::instance($metadataMap->get(class: $value), \Jield\ApiTools\Hal\Metadata\Metadata::class),
                    renderEmbeddedEntities: $this->getRenderEmbeddedEntities(),
                );
            }

            if ($value instanceof Entity) {
                $this->extractEmbeddedEntity(
                    parent: $entity,
                    key: (string) $key,
                    entity: $value,
                    depth: $depth + 1,
                    maxDepth: $maxDepth,
                );
            }

            if ($value instanceof Collection) {
                $this->extractEmbeddedCollection(
                    parent: $entity,
                    key: (string) $key,
                    collection: $value,
                    depth: $depth + 1,
                    maxDepth: $maxDepth,
                );
            }

            if ($value instanceof Link) {
                // We have a link; add it to the entity if it's not already present.
                $entityLinks = $this->injectPropertyAsLink(link: $value, links: $entityLinks);
                unset($entity[$key]);
            }

            if ($value instanceof LinkCollection) {
                /** @var Link $link */
                foreach ($value as $link) {
                    $entityLinks = $this->injectPropertyAsLink(link: $link, links: $entityLinks);
                }

                unset($entity[$key]);
            }
        }

        $halEntity->setLinks(links: $entityLinks);
        $entity['_links'] = $this->fromResource(resource: $halEntity);

        $payload = new ArrayObject( $entity);
        $this->getEventManager()->trigger(eventName: __FUNCTION__ . '.post', target: $this, argv: [
            'payload' => $payload,
            'entity' => $halEntity,
        ]);

        if (isset($entityHash)) {
            unset($this->entityHashStack[$entityHash]);
        }

        return $payload->getArrayCopy();
    }

    public function createEntity(mixed $entity, string $route, string $routeIdentifierName): Entity|Collection
    {
        $metadataMap = $this->getMetadataMap();

        if (is_object($entity) && $metadataMap->has($entity)) {
            /** @psalm-suppress PossiblyFalseArgument,ArgumentTypeCoercion */
            $halEntity = $this->getResourceFactory()->createEntityFromMetadata($entity, \Jield\Stdlib\Value::instance($metadataMap->get($entity), \Jield\ApiTools\Hal\Metadata\Metadata::class));
        } elseif (!$entity instanceof \Jield\ApiTools\Hal\Entity) {
            /** @var mixed $id */
            $id = $this->getIdFromEntity(\Jield\Stdlib\Value::arrayOrObject($entity)) ?: null;
            $halEntity = new Entity(\Jield\Stdlib\Value::arrayOrObject($entity), $id);
        } else {
            $halEntity = $entity;
        }

        $metadata = is_object($entity) && $metadataMap->has($entity) ? $metadataMap->get($entity) : false;

        if (!$metadata || $metadata->getForceSelfLink()) {
            $this->injectSelfLink($halEntity, $route, $routeIdentifierName);
        }

        return $halEntity;
    }

    /**
     * Generate HAL links from a LinkCollection
     */
    public function fromLinkCollection(LinkCollection $collection): array
    {
        return \Jield\Stdlib\Value::present($this->linkCollectionExtractor)->extract(collection: $collection);
    }

    public function fromLink(\Jield\ApiTools\Hal\Link\Link $linkDefinition): array
    {
        $this->getEventManager()->trigger(__FUNCTION__ . '.pre', $this, ['linkDefinition' => $linkDefinition]);

        $linkExtractor = \Jield\Stdlib\Value::present($this->linkCollectionExtractor)->getLinkExtractor();

        return $linkExtractor->extract($linkDefinition);
    }

    /**
     * Create HAL links "object" from an entity or collection
     */
    public function fromResource(LinkCollectionAwareInterface $resource): array
    {
        return $this->fromLinkCollection(collection: $resource->getLinks());
    }

    /**
     * Creates a Collection instance with a static relational link if necessary
     */
    public function createCollection(Paginator|iterable $collection, ?string $route = null): array|Collection
    {
        $metadataMap = $this->getMetadataMap();
        if (is_object(value: $collection) && $metadataMap->has(class: $collection)) {
            /** @psalm-suppress PossiblyFalseArgument */
            $collection = $this->getResourceFactory()->createCollectionFromMetadata(
                object: $collection,
                metadata: \Jield\Stdlib\Value::instance($metadataMap->get(class: $collection), \Jield\ApiTools\Hal\Metadata\Metadata::class),
            );
        }

        if (!$collection instanceof Collection) {
            $collection = new Collection(collection: $collection);
        }

        $metadata = $metadataMap->get(class: $collection);
        /** @psalm-suppress RedundantConditionGivenDocblockType */
        if (!$metadata || $metadata->getForceSelfLink()) {
            $this->injectSelfLink(resource: $collection, route: \Jield\Stdlib\Value::string($route));
        }

        return $collection;
    }

    /**
     * Inject a "self" relational link based on the route and identifier
     */
    public function injectSelfLink(
        LinkCollectionAwareInterface $resource,
        string $route,
        string $routeIdentifier = 'id',
    ): void {
        $this->getSelfLinkInjector()->injectSelfLink(
            resource: $resource,
            route: $route,
            routeIdentifier: $routeIdentifier,
        );
    }

    /**
     * Generate HAL links for a paginated collection
     *
     */
    protected function injectPaginationLinks(Collection $halCollection): bool|ApiProblem
    {
        return $this->getPaginationInjector()->injectPaginationLinks(halCollection: $halCollection);
    }

    /**
     * Extracts and renders an Entity and embeds it in the parent
     * representation
     *
     * Removes the key from the parent representation, and creates a
     * representation for the key in the _embedded object.
     */
    protected function extractEmbeddedEntity(
        array &$parent,
        string $key,
        Entity $entity,
        int $depth = 0,
        ?int $maxDepth = null,
    ): void {
        // No need to increment depth for this call
        $rendered = $this->renderEntity(halEntity: $entity, renderEntity: true, depth: $depth, maxDepth: $maxDepth);

        if (!isset($parent['_embedded'])) {
            $parent['_embedded'] = [];
        }

        \Jield\Stdlib\Value::setPath($parent, ['_embedded', $key], $rendered);
        unset($parent[$key]);
    }

    /**
     * Extracts and renders a Collection and embeds it in the parent
     * representation
     *
     * Removes the key from the parent representation, and creates a
     * representation for the key in the _embedded object.
     *
     * @param string $key
     * @param int $depth depth of the current rendering recursion
     * @param int|null $maxDepth maximum rendering depth for the current metadata
     */
    protected function extractEmbeddedCollection(
        array &$parent,
        string $key,
        Collection $collection,
        int $depth = 0,
        ?int $maxDepth = null,
    ): void {
        $rendered = $this->extractCollection(halCollection: $collection, depth: $depth + 1, maxDepth: $maxDepth);

        if (!isset($parent['_embedded'])) {
            $parent['_embedded'] = [];
        }

        \Jield\Stdlib\Value::setPath($parent, ['_embedded', $key], $rendered);
        unset($parent[$key]);
    }

    /**
     * Extract a collection as an array
     *
     * @param int $depth depth of the current rendering recursion
     * @param int|null $maxDepth maximum rendering depth for the current metadata
     * @todo   Remove 'resource' from event parameters for 1.0.0
     * @todo   Remove trigger of 'renderCollection.resource' for 1.0.0
     */
    protected function extractCollection(Collection $halCollection, int $depth = 0, ?int $maxDepth = null): array
    {
        $collection = [];
        $events = $this->getEventManager();
        $routeIdentifierName = $halCollection->getRouteIdentifierName();
        $entityRoute = $halCollection->getEntityRoute();
        $entityRouteParams = $halCollection->getEntityRouteParams();
        $entityRouteOptions = $halCollection->getEntityRouteOptions();
        $metadataMap = $this->getMetadataMap();

        /** @var mixed $entity */
        foreach ($halCollection->getCollection() as $entity) {
            /** @psalm-var ArrayObject<string, mixed> $eventParams */
            $eventParams = new ArrayObject( [
                'collection' => $halCollection,
                'entity' => $entity,
                'resource' => $entity,
                'route' => $entityRoute,
                'routeParams' => $entityRouteParams,
                'routeOptions' => $entityRouteOptions,
            ]);
            $events->trigger(eventName: 'renderCollection.resource', target: $this, argv: $eventParams);
            $events->trigger(eventName: 'renderCollection.entity', target: $this, argv: $eventParams);

            /** @var iterable|string $entity */
            $entity = $eventParams['entity'];

            /** @psalm-suppress RedundantConditionGivenDocblockType */
            if (is_object(value: $entity) && $metadataMap->has(class: $entity)) {
                /** @psalm-suppress PossiblyFalseArgument,ArgumentTypeCoercion */
                $entity = $this->getResourceFactory()->createEntityFromMetadata(
                    object: $entity,
                    metadata: \Jield\Stdlib\Value::instance($metadataMap->get(class: $entity), \Jield\ApiTools\Hal\Metadata\Metadata::class),
                );
            }

            if ($entity instanceof Entity) {
                // Depth does not increment at this level
                $collection[] = $this->renderEntity(
                    halEntity: $entity,
                    renderEntity: $this->getRenderCollections(),
                    depth: $depth,
                    maxDepth: $maxDepth,
                );
                continue;
            }

            /** @psalm-suppress RedundantConditionGivenDocblockType */
            if (!is_array(value: $entity)) {
                $entity = $this->getEntityExtractor()->extract(object: \Jield\Stdlib\Value::object($entity));
            }

            /** @var mixed $value */
            foreach ($entity as $key => $value) {
                if (is_object(value: $value) && $metadataMap->has(class: $value)) {
                    /** @psalm-suppress PossiblyFalseArgument,ArgumentTypeCoercion */
                    $value = $this->getResourceFactory()->createEntityFromMetadata(
                        object: $value,
                        metadata: \Jield\Stdlib\Value::instance($metadataMap->get(class: $value), \Jield\ApiTools\Hal\Metadata\Metadata::class),
                    );
                }

                if ($value instanceof Entity) {
                    $this->extractEmbeddedEntity(
                        parent: $entity,
                        key: (string) $key,
                        entity: $value,
                        depth: $depth + 1,
                        maxDepth: $maxDepth,
                    );
                }

                if ($value instanceof Collection) {
                    $this->extractEmbeddedCollection(
                        parent: $entity,
                        key: (string) $key,
                        collection: $value,
                        depth: $depth + 1,
                        maxDepth: $maxDepth,
                    );
                }
            }

            /** @var mixed $id */
            $id = $this->getIdFromEntity(entity: $entity);

            if ($id === false) {
                // Cannot handle entities without an identifier
                // Return as-is
                $collection[] = $entity;
                continue;
            }

            if ($eventParams['entity'] instanceof LinkCollectionAwareInterface) {
                $links = $eventParams['entity']->getLinks();
            } else {
                $links = new LinkCollection();
            }

            if (isset($entity['links']) && $entity['links'] instanceof LinkCollection) {
                $links = $entity['links'];
            }

            /* $entity is always an array here. We don't have metadata config for arrays so the static link is forced
             * by default (at the moment) and should be removed manually if not required. But at some point it should
             * be discussed if it makes sense to force static links in this particular use-case.  */
            $selfLink = new Link(relation: 'self');

            /** @var null|array $routeOptions */
            $routeOptions = $eventParams['routeOptions'] ?? null;
            $selfLink->setRoute(
                route: (string) $eventParams['route'],
                params: array_merge(\Jield\Stdlib\Value::toArray($eventParams['routeParams']), [$routeIdentifierName => $id]),
                options: $routeOptions,
            );
            $links->remove('self');
            $links->idempotentAdd(link: $selfLink);

            $entity['_links'] = $this->fromLinkCollection(collection: $links);

            $collection[] = $entity;
        }

        return $collection;
    }

    /**
     * Retrieve the identifier from an entity
     *
     * Expects an "id" member to exist; if not, a boolean false is returned.
     *
     * Triggers the "getIdFromEntity" event with the entity; listeners can
     * return a non-false, non-null value in order to specify the identifier
     * to use for URL assembly.
     *
     * @param object|array $entity
     * @return mixed
     * @todo   Remove 'resource' from parameters sent to event for 1.0.0
     * @todo   Remove trigger of getIdFromResource for 1.0.0
     */
    protected function getIdFromEntity(object|array $entity): mixed
    {
        $params = [
            'entity' => $entity,
            'resource' => $entity,
        ];

        $callback = static fn(mixed $r): bool => null !== $r && false !== $r;

        $results = $this->getEventManager()->triggerEventUntil(
            callback: $callback,
            event: new Event(name: __FUNCTION__, target: $this, params: $params),
        );

        if ($results->stopped()) {
            return $results->last();
        }

        $results = $this->getEventManager()->triggerEventUntil(
            callback: $callback,
            event: new Event(name: 'getIdFromResource', target: $this, params: $params),
        );

        if ($results->stopped()) {
            return $results->last();
        }

        return false;
    }

    /**
     * Reset entity hash stack
     *
     * Call this method if you are rendering multiple responses within the same
     * request cycle that may encounter the same entity instances.
     *
     * @return void
     */
    public function resetEntityHashStack(): void
    {
        $this->entityHashStack = [];
    }

    /**
     * Inject a property-based link into the link collection.
     *
     * Ensures that the link hasn't been previously injected.
     *
     * @param Link|Link[] $link
     * @throws Exception\InvalidArgumentException If a non-link is provided.
     */
    protected function injectPropertyAsLink(Link|array $link, LinkCollection $links): LinkCollection
    {
        if (is_array(value: $link)) {
            foreach ($link as $single) {
                $links = $this->injectPropertyAsLink(link: $single, links: $links);
            }

            return $links;
        }

        

        $links->idempotentAdd(link: $link);

        return $links;
    }
}
