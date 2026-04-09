<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal;

use Closure;
use Jield\ApiTools\Hal\Extractor\EntityExtractor;
use Jield\ApiTools\Hal\Link\Link;
use Jield\ApiTools\Hal\Link\LinkCollection;
use Jield\ApiTools\Hal\Metadata\Metadata;
use Laminas\Paginator\Paginator;
use function array_merge;
use function get_debug_type;
use function is_callable;
use function is_object;
use function sprintf;

class ResourceFactory
{
    public function __construct(protected EntityHydratorManager $entityHydratorManager, protected EntityExtractor $entityExtractor)
    {
    }

    /**
     * Create a entity and/or collection based on a metadata map
     */
    public function createEntityFromMetadata(Paginator|iterable $object, Metadata $metadata, bool $renderEmbeddedEntities = true): Entity|Collection
    {
        if ($metadata->isCollection()) {
            return $this->createCollectionFromMetadata(object: $object, metadata: $metadata);
        }

        /** @psalm-var array<string,mixed> $data */
        $data = $this->entityExtractor->extract(object: $object);

        $entityIdentifierName = $metadata->getEntityIdentifierName();
        if ($entityIdentifierName && !isset($data[$entityIdentifierName])) {
            throw new Exception\RuntimeException(message: sprintf(
                'Unable to determine entity identifier for object of type "%s"; no fields matching "%s"',
                get_debug_type(value: $object),
                $entityIdentifierName
            ));
        }

        /** @var string|null $id */
        $id = $entityIdentifierName !== '' && $entityIdentifierName !== '0' ? $data[$entityIdentifierName] : null;

        if (!$renderEmbeddedEntities) {
            $object = [];
        }

        $halEntity = new Entity(entity: $object, id: $id);

        $links = $halEntity->getLinks();
        $this->marshalMetadataLinks(metadata: $metadata, links: $links);

        $forceSelfLink = $metadata->getForceSelfLink();
        if ($forceSelfLink && !$links->has(relation: 'self')) {
            $link = $this->marshalLinkFromMetadata(
                metadata: $metadata,
                object: $object,
                id: $id,
                routeIdentifierName: $metadata->getRouteIdentifierName()
            );
            $links->add(link: $link);
        }

        return $halEntity;
    }

    public function createCollectionFromMetadata(Paginator|iterable $object, Metadata $metadata): Collection
    {
        $halCollection = new Collection(collection: $object);
        $halCollection->setCollectionName(name: $metadata->getCollectionName());
        $halCollection->setCollectionRoute(route: $metadata->getRoute());
        $halCollection->setEntityRoute(route: $metadata->getEntityRoute());
        $halCollection->setRouteIdentifierName(identifier: $metadata->getRouteIdentifierName());
        $halCollection->setEntityIdentifierName(identifier: $metadata->getEntityIdentifierName());

        $links = $halCollection->getLinks();
        $this->marshalMetadataLinks(metadata: $metadata, links: $links);

        $forceSelfLink = $metadata->getForceSelfLink();
        if (
            $forceSelfLink && !$links->has(relation: 'self')
            && ($metadata->hasUrl() || $metadata->hasRoute())
        ) {
            $link = $this->marshalLinkFromMetadata(metadata: $metadata, object: $object);
            $links->add(link: $link);
        }

        return $halCollection;
    }

    /**
     * Creates a link object, given metadata and a resource
     */
    public function marshalLinkFromMetadata(
        Metadata           $metadata,
        Paginator|iterable $object,
        ?string            $id = null,
        ?string            $routeIdentifierName = null,
        string             $relation = 'self'
    ): Link
    {
        $link = new Link(relation: $relation);
        if ($metadata->hasUrl()) {
            $link->setUrl(href: $metadata->getUrl());
            return $link;
        }

        if (!$metadata->hasRoute()) {
            throw new Exception\RuntimeException(message: sprintf(
                'Unable to create a self link for resource of type "%s"; metadata does not contain a route or a href',
                get_debug_type(value: $object)
            ));
        }

        $params = $metadata->getRouteParams();

        // process any callbacks
        foreach ($params as $key => $param) {
            // bind to the object
            if ($param instanceof Closure && is_object(value: $object)) {
                $param = $param->bindTo(newThis: $object);
            }

            // invoke callables with the object
            if (is_callable(value: $param)) {
                $value        = $param($object);
                $params[$key] = $value;
            }
        }

        if ($routeIdentifierName) {
            $params = array_merge($params, [$routeIdentifierName => $id]);
        }

        $link->setRoute(route: $metadata->getRoute(), params: $params, options: $metadata->getRouteOptions());
        return $link;
    }

    /**
     * Inject any links found in the metadata into the resource's link collection
     *
     */
    public function marshalMetadataLinks(Metadata $metadata, LinkCollection $links): void
    {
        foreach ($metadata->getLinks() as $linkData) {
            $link = Link::factory(spec: $linkData);
            $links->add(link: $link);
        }
    }
}
