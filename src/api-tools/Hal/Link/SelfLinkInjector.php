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
     * @throws \Throwable
     */
    #[Override]
    public function injectSelfLink(
        LinkCollectionAwareInterface $resource,
        string $route,
        string $routeIdentifier = 'id',
    ): void {
        $links = $resource->getLinks();
        if ($links->has(relation: 'self')) {
            return;
        }

        $selfLink = $this->createSelfLink(resource: $resource, route: $route, routeIdentifier: $routeIdentifier);

        $links->remove('self');

        $links->idempotentAdd(link: $selfLink);
    }

    /**
     * @psalm-param string|array{
     *     name:string,params:string|array<array-key,mixed>,options:string|array<array-key,mixed>
     * } $route
     * @throws \Throwable
     */
    private function createSelfLink(
        Entity|array|Collection|LinkCollectionAwareInterface|null $resource,
        $route,
        string $routeIdentifier,
    ): Link {
        /** @psalm-var array|Collection|Entity|null $resource */
        // build route
        if (!is_array(value: $route)) {
            $route = ['name' => $route];
        }

        $routeParams = $this->getRouteParams(resource: $resource, routeIdentifier: $routeIdentifier);
        if ($routeParams !== []) {
            $route['params'] = $routeParams;
        }

        $routeOptions = $this->getRouteOptions(resource: $resource);
        if ($routeOptions !== []) {
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
            'rel' => 'self',
            'route' => $route,
        ];

        return Link::factory(spec: $spec);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function getRouteParams(Entity|array|Collection|null $resource, string $routeIdentifier): array
    {
        if ($resource instanceof Collection) {
            return $resource->getCollectionRouteParams();
        }

        $routeParams = [];

        if ($resource instanceof Entity && null !== $resource->getId()) {
            $routeParams = [
                $routeIdentifier => $resource->getId(),
            ];
        }

        return $routeParams;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function getRouteOptions(Entity|array|Collection|null $resource): array
    {
        if ($resource instanceof Collection) {
            return $resource->getCollectionRouteOptions();
        }

        return [];
    }
}
