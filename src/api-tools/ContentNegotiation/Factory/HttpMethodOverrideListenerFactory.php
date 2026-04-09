<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation\Factory;

use Psr\Container\ContainerInterface;
use Jield\ApiTools\ContentNegotiation\ContentNegotiationOptions;
use Jield\ApiTools\ContentNegotiation\HttpMethodOverrideListener;

class HttpMethodOverrideListenerFactory
{
    public function __invoke(ContainerInterface $container): HttpMethodOverrideListener
    {
        $options             = $container->get(ContentNegotiationOptions::class);
        $httpOverrideMethods = $options->getHttpOverrideMethods();
        return new HttpMethodOverrideListener(httpMethodOverride: $httpOverrideMethods);
    }
}
