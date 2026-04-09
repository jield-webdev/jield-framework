<?php

declare(strict_types=1);

namespace Jield\ApiTools\Versioning\Factory;

use Jield\ApiTools\Versioning\ContentTypeListener;
use Psr\Container\ContainerInterface;

class ContentTypeListenerFactory
{
    public function __invoke(ContainerInterface $container): ContentTypeListener
    {
        $config = $container->has(id: 'config') ? $container->get('config') : [];
        $config = $config['api-tools-versioning']['content-type'] ?? [];

        $listener = new ContentTypeListener();
        foreach ($config as $regexp) {
            $listener->addRegexp(regex: $regexp);
        }

        return $listener;
    }
}
