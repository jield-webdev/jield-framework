<?php

declare(strict_types=1);

namespace Jield\ApiTools\Versioning\Factory;

use Jield\ApiTools\Versioning\AcceptListener;
use Jield\Stdlib\Value;
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
        foreach (Value::iterable($config) as $regexp) {
            $listener->addRegexp(regex: Value::string($regexp));
        }

        return $listener;
    }
}
