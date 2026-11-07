<?php

declare(strict_types=1);

namespace Jield\Authorize\Factory;

use Doctrine\Persistence\ObjectManager;
use Jield\Authorize\Config;
use Jield\Authorize\Exception\InvalidArgumentException;
use Jield\Authorize\Provider\Role\ObjectRepositoryProvider;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

/**
 * Factory responsible of instantiating {@see \Jield\Authorize\Provider\Role\ObjectRepositoryProvider}
 */
class ObjectRepositoryRoleProviderFactory implements FactoryInterface
{
    /**
     * @throws InvalidArgumentException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null,
    ): ObjectRepositoryProvider {
        $config = Assert::isArray($container->get(Config::class));

        if (($config['role_providers'][ObjectRepositoryProvider::class] ?? null) === null) {
            throw new InvalidArgumentException(
                message: 'Config for "Jield\Authorize\Provider\Role\ObjectRepositoryProvider" not set',
            );
        }

        /** @mago-var mixed $providerConfig */
        $providerConfig = $config['role_providers'][ObjectRepositoryProvider::class];

        if (($providerConfig['role_entity_class'] ?? null) === null) {
            throw new InvalidArgumentException(
                message: 'role_entity_class not set in the bjyauthorize role_providers config.',
            );
        }

        if (($providerConfig['object_manager'] ?? null) === null) {
            throw new InvalidArgumentException(
                message: 'object_manager not set in the bjyauthorize role_providers config.',
            );
        }

        /** @var ObjectManager $objectManager */
        $objectManager = $container->get(Assert::string($providerConfig['object_manager']));

        return new ObjectRepositoryProvider(objectRepository: $objectManager->getRepository(className: \Jield\Stdlib\Value::classString(
            $providerConfig['role_entity_class'],
        )));
    }
}
