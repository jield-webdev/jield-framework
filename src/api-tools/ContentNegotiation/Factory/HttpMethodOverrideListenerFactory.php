<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation\Factory;

use Jield\ApiTools\ContentNegotiation\ContentNegotiationOptions;
use Jield\ApiTools\ContentNegotiation\HttpMethodOverrideListener;
use Psr\Container\ContainerInterface;

class HttpMethodOverrideListenerFactory
{
    public function __invoke(ContainerInterface $container): HttpMethodOverrideListener
    {
        $options = \Jield\Stdlib\Service::get($container, ContentNegotiationOptions::class);
        $httpOverrideMethods = $options->getHttpOverrideMethods();
        return new HttpMethodOverrideListener(httpMethodOverride: $httpOverrideMethods);
    }
}
