<?php

declare(strict_types=1);

namespace Jield\Authorize\Service;

use L\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Factory responsible of retrieving an array containing the BjyAuthorize configuration
 */
class ConfigServiceFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @see \Laminas\ServiceManager\Factory\FactoryInterface::__invoke()
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): mixed
    {
        $config = $container->get('config');

        return $config['bjyauthorize'];
    }
}
