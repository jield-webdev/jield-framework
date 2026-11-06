<?php

declare(strict_types=1);

namespace Jield\Authorize\Factory;

use Jield\Authorize\Service\AssertionService;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

final class AssertionServiceFactory implements FactoryInterface
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): AssertionService
    {
        return new AssertionService(container: \Jield\Stdlib\Value::instance($container, \Laminas\ServiceManager\ServiceManager::class));
    }
}
