<?php

declare(strict_types=1);

namespace Jield\Mvc\Factory;

// phpcs:ignore
use Jield\Mvc\View\Http\InjectTemplateListener;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

use function is_array;

class InjectTemplateListenerFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * Create and return an InjectTemplateListener instance.
     */
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): InjectTemplateListener {
        $listener = new InjectTemplateListener();
        /** @mago-var mixed $config */
        $config = $container->get('config');

        if (
            ($config['view_manager']['controller_map'] ?? null) !== null
            && is_array($config['view_manager']['controller_map'])
        ) {
            $listener->setControllerMap($config['view_manager']['controller_map']);
        }

        return $listener;
    }
}
