<?php

declare(strict_types=1);

namespace Jield\Authorize\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

/**
 * Factory responsible of building the {@see \Jield\Authorize\Service\AuthorizeService} service
 */
class AuthorizeFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @see \Laminas\ServiceManager\Factory\FactoryInterface::__invoke()
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): object|AuthorizeService
    {
        return new AuthorizeService(config: $container->get('Jield\Authorize\Config'), serviceLocator: $container);
    }
}
