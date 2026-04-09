<?php

declare(strict_types=1);

namespace Jield\Authorize\Service;

use Jield\Authorize\Guard\Controller;
use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Factory responsible of instantiating {@see \Jield\Authorize\Guard\Controller}
 */
class ControllerGuardServiceFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @see \Laminas\ServiceManager\Factory\FactoryInterface::__invoke()
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): object|Controller
    {
        return new Controller(
            rules: $container->get('Jield\Authorize\Config')['guards'][Controller::class],
            container: $container
        );
    }
}
