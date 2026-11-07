<?php

declare(strict_types=1);

namespace Jield\Authorize\Factory;

use Jield\Authorize\Service\AuthorizeService;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

final class AuthorizeServiceFactory implements FactoryInterface
{
    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): AuthorizeService
    {
        return new AuthorizeService(container: $container);
    }
}
