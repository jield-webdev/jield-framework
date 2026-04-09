<?php

declare(strict_types=1);

namespace Jield\Authorize\Service;

use Jield\Authorize\Exception\InvalidArgumentException;
use Jield\Authorize\Provider\Role\ObjectRepositoryProvider;
use Doctrine\Persistence\ObjectManager;
use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Factory responsible of instantiating {@see \Jield\Authorize\Provider\Role\ObjectRepositoryProvider}
 */
class ObjectRepositoryRoleProviderFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): ObjectRepositoryProvider
    {
        $config = $container->get('Jield\Authorize\Config');

        if (!isset($config['role_providers'][ObjectRepositoryProvider::class])) {
            throw new InvalidArgumentException(
                message: 'Config for "Jield\Authorize\Provider\Role\ObjectRepositoryProvider" not set'
            );
        }

        $providerConfig = $config['role_providers'][ObjectRepositoryProvider::class];

        if (!isset($providerConfig['role_entity_class'])) {
            throw new InvalidArgumentException(message: 'role_entity_class not set in the bjyauthorize role_providers config.');
        }

        if (!isset($providerConfig['object_manager'])) {
            throw new InvalidArgumentException(message: 'object_manager not set in the bjyauthorize role_providers config.');
        }

        /** @var ObjectManager $objectManager */
        $objectManager = $container->get($providerConfig['object_manager']);

        return new ObjectRepositoryProvider(objectRepository: $objectManager->getRepository(className: $providerConfig['role_entity_class']));
    }
}
