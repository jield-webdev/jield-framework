<?php

namespace Jield\Mvc\Controller\Plugin\Service;

// phpcs:ignore
use Psr\Container\ContainerInterface;
use Jield\Mvc\Controller\Plugin\Forward;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Factory\FactoryInterface;

use function sprintf;

class ForwardFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return Forward
     * @throws ServiceNotCreatedException If Controllermanager service is not found in application service locator.
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): mixed
    {
        if (! $container->has(id: 'ControllerManager')) {
            throw new ServiceNotCreatedException(message: sprintf(
                '%s requires that the application service manager contains a "%s" service; none found',
                self::class,
                'ControllerManager'
            ));
        }
        $controllers = $container->get('ControllerManager');

        return new Forward(controllers: $controllers);
    }
}
