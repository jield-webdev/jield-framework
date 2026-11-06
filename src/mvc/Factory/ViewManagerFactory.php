<?php

declare(strict_types=1);

namespace Jield\Mvc\Factory;

// phpcs:ignore
use Jield\Mvc\View\Http\ViewManager as HttpViewManager;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class ViewManagerFactory implements FactoryInterface
{
    /**
     * Create and return a view manager.
     *
     * @param  string $requestedName
     * @param  null|array $options
     * @return HttpViewManager
     */
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): HttpViewManager {
        return \Jield\Stdlib\Value::instance($container->get('HttpViewManager'), \Jield\Mvc\View\Http\ViewManager::class);
    }
}
