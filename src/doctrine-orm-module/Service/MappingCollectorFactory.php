<?php

declare(strict_types=1);

namespace DoctrineORMModule\Service;

use BadMethodCallException;
use DoctrineModule\Service\AbstractFactory;
use Jield\Stdlib\Value;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Psr\Container\ContainerInterface;

/**
 * Service factory responsible for instantiating {@see \DoctrineORMModule\Collector\MappingCollector}
 */
final class MappingCollectorFactory extends AbstractFactory
{
    /**
     * @throws \ReflectionException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): object
    {
        $collectorClass = 'DoctrineORMModule\\Collector\\MappingCollector';
        if (!class_exists($collectorClass)) {
            throw new ServiceNotCreatedException(
                'Configure a mapping collector compatible with your developer toolbar.',
            );
        }
        $name = $this->getName();
        /** @mago-var mixed $objectManager */
        $objectManager = $container->get('doctrine.entitymanager.' . $name);

        return Value::instantiate($collectorClass, [
            Value::call($objectManager, 'getMetadataFactory'),
            'doctrine.mapping_collector.' . $name,
        ]);
    }

    /**
     * @throws BadMethodCallException
     */
    public function getOptionsClass(): string
    {
        throw new BadMethodCallException();
    }
}
