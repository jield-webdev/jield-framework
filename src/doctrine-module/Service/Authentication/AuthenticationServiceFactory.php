<?php

declare(strict_types=1);

namespace DoctrineModule\Service\Authentication;

use BadMethodCallException;
use DoctrineModule\Service\AbstractFactory;
use Laminas\Authentication\AuthenticationService;
use Psr\Container\ContainerInterface;

/**
 * Factory to create authentication service object.
 */
final class AuthenticationServiceFactory extends AbstractFactory
{
    /**
     * {@inheritDoc}
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null,
    ): AuthenticationService {
        return new AuthenticationService(
            storage: $container->get('doctrine.authenticationstorage.' . $this->getName()),
            adapter: $container->get('doctrine.authenticationadapter.' . $this->getName()),
        );
    }

    public function getOptionsClass(): string
    {
        throw new BadMethodCallException(message: 'Not implemented');
    }
}
