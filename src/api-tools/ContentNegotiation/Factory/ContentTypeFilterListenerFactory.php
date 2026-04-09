<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation\Factory;

use Psr\Container\ContainerInterface;
use Jield\ApiTools\ContentNegotiation\ContentNegotiationOptions;
use Jield\ApiTools\ContentNegotiation\ContentTypeFilterListener;

class ContentTypeFilterListenerFactory
{
    public function __invoke(ContainerInterface $container): ContentTypeFilterListener
    {
        $listener = new ContentTypeFilterListener();

        $options = $container->get(ContentNegotiationOptions::class);
        $listener->setConfig(config: $options->getContentTypeWhitelist());

        return $listener;
    }
}
