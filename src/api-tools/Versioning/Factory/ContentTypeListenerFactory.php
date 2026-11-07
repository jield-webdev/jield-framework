<?php

declare(strict_types=1);

namespace Jield\ApiTools\Versioning\Factory;

use Jield\ApiTools\Versioning\ContentTypeListener;
use Jield\Stdlib\Value;
use Psr\Container\ContainerInterface;

class ContentTypeListenerFactory
{
    public function __invoke(ContainerInterface $container): ContentTypeListener
    {
        /** @var mixed $config */
        $config = $container->has('config') ? $container->get('config') : [];
        /** @var mixed $config */
        $config = $config['api-tools-versioning']['content-type'] ?? [];

        $listener = new ContentTypeListener();
        foreach (Value::iterable($config) as $regexp) {
            $listener->addRegexp(regex: Value::string($regexp));
        }

        return $listener;
    }
}
