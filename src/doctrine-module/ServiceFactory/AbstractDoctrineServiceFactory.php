<?php

declare(strict_types=1);

namespace DoctrineModule\ServiceFactory;

use Jield\Stdlib\Value;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use Psr\Container\ContainerInterface;

use function preg_match;

/**
 * Abstract service factory capable of instantiating services whose names match the
 * pattern <code>doctrine.$serviceType.$serviceName</doctrine>
 */
final class AbstractDoctrineServiceFactory implements AbstractFactoryInterface
{
    /**
     * {@inheritDoc}
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function canCreate(ContainerInterface $container, $requestedName): bool
    {
        return $this->getFactoryMapping(serviceLocator: $container, name: $requestedName) !== false;
    }

    /**
     * {@inheritDoc}
     * @throws \ReflectionException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): mixed
    {
        $mappings = $this->getFactoryMapping(serviceLocator: $container, name: $requestedName);

        if (!is_array($mappings)) {
            throw new ServiceNotFoundException();
        }

        /** @mago-var mixed $factoryClass */
        $factoryClass = $mappings['factoryClass'];
        return Value::call(
            Value::instantiate($factoryClass, [$mappings['serviceName']]),
            '__invoke',
            [$container, $requestedName],
        );
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    private function getFactoryMapping(ContainerInterface $serviceLocator, string $name): array|bool
    {
        $matches = [];

        if (!preg_match(
            pattern: '/^doctrine\.((?<mappingType>orm|odm)\.|)(?<serviceType>[a-z0-9_]+)\.(?<serviceName>[a-z0-9_]+)$/',
            subject: $name,
            matches: $matches,
        )) {
            return false;
        }

        /** @mago-var mixed $config */
        $config = $serviceLocator->get('config');
        $mappingType = $matches['mappingType'];
        $serviceType = $matches['serviceType'];
        $serviceName = $matches['serviceName'];

        if ($mappingType === '') {
            if (
                !isset($config['doctrine_factories'][$serviceType])
                || !isset($config['doctrine'][$serviceType][$serviceName])
            ) {
                return false;
            }

            return [
                'serviceType' => $serviceType,
                'serviceName' => $serviceName,
                'factoryClass' => $config['doctrine_factories'][$serviceType],
            ];
        }

        if (
            !isset(
                $config['doctrine_factories'][$mappingType][$serviceType],
                $config['doctrine'][$mappingType][$serviceType][$serviceName],
            )
        ) {
            return false;
        }

        return [
            'serviceType' => $serviceType,
            'serviceName' => $serviceName,
            'factoryClass' => $config['doctrine_factories'][$mappingType][$serviceType],
        ];
    }
}
