<?php

declare(strict_types=1);

namespace Jield\Authorize\Factory;

use Jield\Authorize\Service\AssertionService;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\ServiceManager\ServiceManager;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Webmozart\Assert\Assert;

final class AssertionServiceFactory implements FactoryInterface
{
    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): AssertionService
    {
        return new AssertionService(container: Assert::isInstanceOf($container, ServiceManager::class));
    }
}
