<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation\Factory;

use Jield\ApiTools\ContentNegotiation\ContentNegotiationOptions;
use Jield\ApiTools\ContentNegotiation\ContentTypeFilterListener;
use Jield\Stdlib\Service;
use Psr\Container\ContainerInterface;

class ContentTypeFilterListenerFactory
{
    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): ContentTypeFilterListener
    {
        $listener = new ContentTypeFilterListener();

        $options = Service::get($container, ContentNegotiationOptions::class);
        $listener->setConfig(config: $options->getContentTypeWhitelist());

        return $listener;
    }
}
