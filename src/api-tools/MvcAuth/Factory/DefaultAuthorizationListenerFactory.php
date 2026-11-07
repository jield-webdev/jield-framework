<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Factory;

use Jield\ApiTools\MvcAuth\Authorization\AuthorizationInterface;
use Jield\ApiTools\MvcAuth\Authorization\DefaultAuthorizationListener;
use Jield\Stdlib\Service;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;

use function sprintf;

/**
 * Factory for creating the DefaultAuthorizationListener from configuration.
 */
class DefaultAuthorizationListenerFactory implements FactoryInterface
{
    /**
     * Create and return the default authorization listener.
     *
     * @param string $requestedName
     * @throws ServiceNotCreatedException If the AuthorizationInterface service is missing.
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null,
    ): DefaultAuthorizationListener {
        if (!$container->has(AuthorizationInterface::class)) {
            throw new ServiceNotCreatedException(message: sprintf(
                'Cannot create %s service; no %s service available!',
                DefaultAuthorizationListener::class,
                AuthorizationInterface::class,
            ));
        }

        $authorization = Service::get($container, AuthorizationInterface::class);

        return new DefaultAuthorizationListener(authorization: $authorization);
    }
}
