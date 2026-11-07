<?php

declare(strict_types=1);

namespace Jield\Mvc\Factory;

use Jield\Stdlib\Value;
use Laminas\Router\Http\TreeRouteStack;
use Laminas\Router\RoutePluginManager;
use Laminas\Router\RouteStackInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

final class HttpRouterFactory implements FactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): RouteStackInterface {
        /** @var mixed $config */
        $config = $container->get('config')['router'] ?? [];
        /** @var mixed $class */
        $class = $config['router_class'] ?? TreeRouteStack::class;
        Value::setPath(
            $config,
            ['route_plugins'],
            $container->get(Value::string($config['route_plugins'] ?? RoutePluginManager::class)),
        );
        return Value::instance($class::factory($config), \Laminas\Router\RouteStackInterface::class);
    }
}
