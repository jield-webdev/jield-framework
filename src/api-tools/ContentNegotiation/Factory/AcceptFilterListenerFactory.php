<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation\Factory;

use Psr\Container\ContainerInterface;
use Jield\ApiTools\ContentNegotiation\AcceptFilterListener;
use Jield\ApiTools\ContentNegotiation\ContentNegotiationOptions;

class AcceptFilterListenerFactory
{
    public function __invoke(ContainerInterface $container): AcceptFilterListener
    {
        $listener = new AcceptFilterListener();

        $options = $container->get(ContentNegotiationOptions::class);
        $listener->setConfig(config: $options->getAcceptWhitelist());

        return $listener;
    }
}
