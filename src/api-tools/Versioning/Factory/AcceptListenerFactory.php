<?php

declare(strict_types=1);

namespace Jield\ApiTools\Versioning\Factory;

use Jield\ApiTools\Versioning\AcceptListener;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

class AcceptListenerFactory
{
    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): AcceptListener
    {
        /** @mago-var mixed $config */
        $config = $container->has('config') ? $container->get('config') : [];
        /** @mago-var mixed $config */
        $config = $config['api-tools-versioning']['content-type'] ?? [];

        $listener = new AcceptListener();
        /** @mago-var mixed $regexp */
        foreach (Assert::isIterable($config) as $regexp) {
            $listener->addRegexp(regex: Assert::string($regexp));
        }

        return $listener;
    }
}
