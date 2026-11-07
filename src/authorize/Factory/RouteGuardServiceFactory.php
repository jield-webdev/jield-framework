<?php

declare(strict_types=1);

namespace Jield\Authorize\Factory;

use Jield\Authorize\Config;
use Jield\Authorize\Guard\Route;
use Jield\Stdlib\Value;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

/**
 * Factory responsible of instantiating {@see \Jield\Authorize\Guard\Route}
 */
class RouteGuardServiceFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @see \Laminas\ServiceManager\Factory\FactoryInterface::__invoke()
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Route
    {
        return new Route(
            rules: \Webmozart\Assert\Assert::isArray(Value::path(
                $container->get(Config::class),
                ['guards', Route::class],
            )),
            container: $container,
        );
    }
}
