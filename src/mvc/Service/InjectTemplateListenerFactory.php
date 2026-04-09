<?php

namespace Jield\Mvc\Service;

// phpcs:ignore
use Psr\Container\ContainerInterface;
use Jield\Mvc\View\Http\InjectTemplateListener;
use Laminas\ServiceManager\Factory\FactoryInterface;

use function is_array;

class InjectTemplateListenerFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * Create and return an InjectTemplateListener instance.
     *
     * @return InjectTemplateListener
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): mixed
    {
        $listener = new InjectTemplateListener();
        $config   = $container->get('config');

        if (
            isset($config['view_manager']['controller_map'])
            && (is_array(value: $config['view_manager']['controller_map']))
        ) {
            $listener->setControllerMap(map: $config['view_manager']['controller_map']);
        }

        return $listener;
    }
}
