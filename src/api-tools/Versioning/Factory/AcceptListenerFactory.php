<?php

declare(strict_types=1);

namespace Jield\ApiTools\Versioning\Factory;

use Jield\ApiTools\Versioning\AcceptListener;
use Psr\Container\ContainerInterface;

class AcceptListenerFactory
{
    public function __invoke(ContainerInterface $container): AcceptListener
    {
        /** @var mixed $config */
        $config = $container->has('config') ? $container->get('config') : [];
        /** @var mixed $config */
        $config = $config['api-tools-versioning']['content-type'] ?? [];

        $listener = new AcceptListener();
        /** @var mixed $regexp */
        foreach (\Jield\Stdlib\Value::iterable($config) as $regexp) {
            $listener->addRegexp(regex: \Jield\Stdlib\Value::string($regexp));
        }

        return $listener;
    }
}
