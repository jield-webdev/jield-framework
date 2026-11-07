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
     */
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): HttpViewManager {
        return \Webmozart\Assert\Assert::isInstanceOf($container->get('HttpViewManager'), \Jield\Mvc\View\Http\ViewManager::class);
    }
}
