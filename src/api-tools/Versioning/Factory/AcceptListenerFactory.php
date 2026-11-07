<?php

declare(strict_types=1);

namespace Jield\ApiTools\Versioning\Factory;

use Jield\ApiTools\Versioning\AcceptListener;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

class AcceptListenerFactory
{
    public function __invoke(ContainerInterface $container): AcceptListener
    {
        $config = $container->has('config') ? $container->get('config') : [];
        $config = $config['api-tools-versioning']['content-type'] ?? [];

        $listener = new AcceptListener();
        foreach (Assert::isIterable($config) as $regexp) {
            $listener->addRegexp(regex: $regexp);
        }

        return $listener;
    }
}
