<?php

declare(strict_types=1);

namespace Jield\ApiTools\Versioning\Factory;

use Jield\ApiTools\Versioning\ContentTypeListener;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

class ContentTypeListenerFactory
{
    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): ContentTypeListener
    {
        /** @mago-var mixed $config */
        $config = $container->has('config') ? $container->get('config') : [];
        /** @mago-var mixed $config */
        $config = $config['api-tools-versioning']['content-type'] ?? [];

        $listener = new ContentTypeListener();
        /** @mago-var mixed $regexp */
        foreach (Assert::isIterable($config) as $regexp) {
            $listener->addRegexp(regex: Assert::string($regexp));
        }

        return $listener;
    }
}
