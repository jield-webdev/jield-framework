<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation\Factory;

use Psr\Container\ContainerInterface;
use Jield\ApiTools\ContentNegotiation\AcceptListener;
use Jield\ApiTools\ContentNegotiation\ContentNegotiationOptions;
use Jield\Mvc\Controller\Plugin\AcceptableViewModelSelector;

class AcceptListenerFactory
{
    public function __invoke(ContainerInterface $container): AcceptListener
    {
        return new AcceptListener(
            selector: $this->getAcceptableViewModelSelector(container: $container),
            config: $container->get(ContentNegotiationOptions::class)->toArray()
        );
    }

    /**
     * Retrieve or generate the AcceptableViewModelSelector plugin instance.
     *
     */
    private function getAcceptableViewModelSelector(ContainerInterface $container): AcceptableViewModelSelector
    {
        if (! $container->has(id: 'ControllerPluginManager')) {
            return new AcceptableViewModelSelector();
        }

        $plugins = $container->get('ControllerPluginManager');
        if (! $plugins->has('AcceptableViewModelSelector')) {
            return new AcceptableViewModelSelector();
        }

        return $plugins->get('AcceptableViewModelSelector');
    }
}
