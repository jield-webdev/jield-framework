<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation\Factory;

use Jield\ApiTools\ContentNegotiation\ContentNegotiationOptions;
use Jield\ApiTools\ContentNegotiation\ContentTypeFilterListener;
use Psr\Container\ContainerInterface;

class ContentTypeFilterListenerFactory
{
    public function __invoke(ContainerInterface $container): ContentTypeFilterListener
    {
        $listener = new ContentTypeFilterListener();

        $options = \Jield\Stdlib\Service::get($container, ContentNegotiationOptions::class);
        $listener->setConfig(config: $options->getContentTypeWhitelist());

        return $listener;
    }
}
