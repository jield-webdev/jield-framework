<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Link;

use Jield\ApiTools\Hal\Collection;
use Jield\ApiTools\Hal\Entity;

use Override;
use function is_array;

class SelfLinkInjector implements SelfLinkInjectorInterface
{
    /**
     * {@inheritDoc}
     */
    #[Override]
    public function injectSelfLink(LinkCollectionAwareInterface $resource, string $route, string $routeIdentifier = 'id'): void
    {
        $links = $resource->getLinks();
        if ($links->has(relation: 'self')) {
            return;
        }

        $selfLink = $this->createSelfLink(resource: $resource, route: $route, routeIdentifier: $routeIdentifier);

        $links->add(link: $selfLink, overwrite: true);
    }

    /**
     * @param array|Collection|Entity|LinkCollectionAwareInterface|null $resource
     * @psalm-param string|array{
     *     name:string,params:string|array<array-key,mixed>,options:string|array<array-key,mixed>
     * } $route
     * @param string $routeIdentifier
     */
    private function createSelfLink(Entity|array|Collection|LinkCollectionAwareInterface|null $resource, $route, string $routeIdentifier): Link
    {
        /** @psalm-var array|Collection|Entity|null $resource */
        // build route
        if (! is_array(value: $route)) {
            $route = ['name' => (string) $route];
        }

        $routeParams = $this->getRouteParams(resource: $resource, routeIdentifier: $routeIdentifier);
        if ($routeParams !== '' && $routeParams !== '0' && $routeParams !== []) {
            $route['params'] = $routeParams;
        }

        $routeOptions = $this->getRouteOptions(resource: $resource);
        if ($routeOptions !== '' && $routeOptions !== '0' && $routeOptions !== []) {
            $route['options'] = $routeOptions;
        }

        /** @psalm-var array{
         *  rel: string|array<array-key,string>,
         *  props?: array<array-key,mixed>,
         *  href?: string,
         *  route?:string|array{name:string,params:string|array<array-key,mixed>,options:string|array<array-key,mixed>},
         *  url?: string
         * } $spec */
        $spec = [
            'rel'   => 'self',
            'route' => $route,
        ];

        return Link::factory(spec: $spec);
    }

    /**
     * @param array|Collection|Entity|null $resource
     * @param string $routeIdentifier
     * @return array|string
     * @psalm-return array<empty, empty>|array<array-key, mixed>|string
     */
    private function getRouteParams(Entity|array|Collection|null $resource, string $routeIdentifier): array|string
    {
        if ($resource instanceof Collection) {
            return $resource->getCollectionRouteParams();
        }

        $routeParams = [];

        if (
            $resource instanceof Entity
            && null !== $resource->getId()
        ) {
            $routeParams = [
                $routeIdentifier => $resource->getId(),
            ];
        }

        return $routeParams;
    }

    /**
     * @param array|Collection|Entity|null $resource
     * @return array|string
     * @psalm-return array<empty, empty>|array<array-key, mixed>|string
     */
    private function getRouteOptions(Entity|array|Collection|null $resource): array|string
    {
        if ($resource instanceof Collection) {
            return $resource->getCollectionRouteOptions();
        }

        return [];
    }
}
