<?php

declare(strict_types=1);

namespace Jield\Authorize\Factory;

use Jield\Authorize\View\UnauthorizedStrategy;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Jield\Authorize\Config;

/**
 * Factory responsible of instantiating {@see \Jield\Authorize\View\UnauthorizedStrategy}
 */
class UnauthorizedStrategyServiceFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @see \Laminas\ServiceManager\Factory\FactoryInterface::__invoke()
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): UnauthorizedStrategy
    {
        return new UnauthorizedStrategy(template: $container->get(Config::class)['template']);
    }
}
