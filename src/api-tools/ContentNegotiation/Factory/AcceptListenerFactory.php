<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation\Factory;

use Jield\ApiTools\ContentNegotiation\AcceptListener;
use Jield\ApiTools\ContentNegotiation\ContentNegotiationOptions;
use Jield\Mvc\Controller\Plugin\AcceptableViewModelSelector;
use Jield\Mvc\Controller\PluginManager;
use Jield\Stdlib\Service;
use Psr\Container\ContainerInterface;

class AcceptListenerFactory
{
    public function __invoke(ContainerInterface $container): AcceptListener
    {
        return new AcceptListener(
            selector: $this->getAcceptableViewModelSelector(container: $container),
            config: Service::get($container, ContentNegotiationOptions::class)->toArray(),
        );
    }

    /**
     * Retrieve or generate the AcceptableViewModelSelector plugin instance.
     *
     */
    private function getAcceptableViewModelSelector(ContainerInterface $container): AcceptableViewModelSelector
    {
        if (!$container->has('ControllerPluginManager')) {
            return new AcceptableViewModelSelector();
        }

        $plugins = Service::get(
            $container,
            PluginManager::class,
            'ControllerPluginManager',
        );
        if (!$plugins->has('AcceptableViewModelSelector')) {
            return new AcceptableViewModelSelector();
        }

        return Service::get($plugins, AcceptableViewModelSelector::class, 'AcceptableViewModelSelector');
    }
}
