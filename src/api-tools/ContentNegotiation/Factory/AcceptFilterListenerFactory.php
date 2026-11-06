<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation\Factory;

use Jield\ApiTools\ContentNegotiation\AcceptFilterListener;
use Jield\ApiTools\ContentNegotiation\ContentNegotiationOptions;
use Psr\Container\ContainerInterface;

class AcceptFilterListenerFactory
{
    public function __invoke(ContainerInterface $container): AcceptFilterListener
    {
        $listener = new AcceptFilterListener();

        $options = \Jield\Stdlib\Service::get($container, ContentNegotiationOptions::class);
        $listener->setConfig(config: $options->getAcceptWhitelist());

        return $listener;
    }
}
