<?php

declare(strict_types=1);

namespace Jield\Mvc\Controller\Plugin\Service;

// phpcs:ignore
use Jield\Mvc\Controller\ControllerManager;
use Jield\Mvc\Controller\Plugin\Forward;
use Jield\Stdlib\Service;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

use function sprintf;

class ForwardFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return Forward
     * @throws ServiceNotCreatedException If Controllermanager service is not found in application service locator.
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Forward
    {
        if (!$container->has('ControllerManager')) {
            throw new ServiceNotCreatedException(sprintf(
                '%s requires that the application service manager contains a "%s" service; none found',
                self::class,
                'ControllerManager',
            ));
        }
        $controllers = Service::get(
            $container,
            ControllerManager::class,
            'ControllerManager',
        );

        return new Forward($controllers);
    }
}
