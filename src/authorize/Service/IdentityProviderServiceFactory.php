<?php

declare(strict_types=1);

namespace Jield\Authorize\Service;

use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class IdentityProviderServiceFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @see \Laminas\ServiceManager\Factory\FactoryInterface::__invoke()
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): mixed
    {
        return $container->get($container->get('Jield\Authorize\Config')['identity_provider']);
    }
}
