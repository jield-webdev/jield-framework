<?php

declare(strict_types=1);

namespace DoctrineModule\Service\Authentication;

use BadMethodCallException;
use DoctrineModule\Service\AbstractFactory;
use Jield\Stdlib\Value;
use Laminas\Authentication\Adapter\AdapterInterface;
use Laminas\Authentication\AuthenticationService;
use Laminas\Authentication\Storage\StorageInterface;
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
            storage: Value::instance($container->get('doctrine.authenticationstorage.' . $this->getName()), StorageInterface::class),
            adapter: Value::instance($container->get('doctrine.authenticationadapter.' . $this->getName()), AdapterInterface::class),
        );
    }

    public function getOptionsClass(): string
    {
        throw new BadMethodCallException(message: 'Not implemented');
    }
}
