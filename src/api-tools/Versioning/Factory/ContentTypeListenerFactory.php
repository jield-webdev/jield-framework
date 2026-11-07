<?php

declare(strict_types=1);

namespace Jield\ApiTools\Versioning\Factory;

use Jield\ApiTools\Versioning\ContentTypeListener;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

class ContentTypeListenerFactory
{
    public function __invoke(ContainerInterface $container): ContentTypeListener
    {
        $config = $container->has('config') ? $container->get('config') : [];
        $config = $config['api-tools-versioning']['content-type'] ?? [];

        $listener = new ContentTypeListener();
        foreach (Assert::isIterable($config) as $regexp) {
            $listener->addRegexp(regex: $regexp);
        }

        return $listener;
    }
}
