<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation\Factory;

use Jield\ApiTools\ContentNegotiation\AcceptListener;
use Jield\ApiTools\ContentNegotiation\ContentNegotiationOptions;
use Jield\Mvc\Controller\Plugin\AcceptableViewModelSelector;
use Psr\Container\ContainerInterface;

class AcceptListenerFactory
{
    public function __invoke(ContainerInterface $container): AcceptListener
    {
        return new AcceptListener(
            selector: $this->getAcceptableViewModelSelector(container: $container),
            config: \Jield\Stdlib\Service::get($container, ContentNegotiationOptions::class)->toArray(),
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

        $plugins = \Jield\Stdlib\Service::get($container, \Jield\Mvc\Controller\PluginManager::class, 'ControllerPluginManager');
        if (!$plugins->has('AcceptableViewModelSelector')) {
            return new AcceptableViewModelSelector();
        }

        return \Jield\Stdlib\Service::get($plugins, AcceptableViewModelSelector::class, 'AcceptableViewModelSelector');
    }
}
