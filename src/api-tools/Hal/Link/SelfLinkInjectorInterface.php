<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Link;

interface SelfLinkInjectorInterface
{
    /**
     * Inject a "self" relational link based on the route and identifier
     */
    public function injectSelfLink(
        LinkCollectionAwareInterface $resource,
        string $route,
        string $routeIdentifier = 'id',
    ): void;
}
