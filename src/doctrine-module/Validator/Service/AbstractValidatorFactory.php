<?php

declare(strict_types=1);

namespace DoctrineModule\Validator\Service;

use BadMethodCallException;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use DoctrineModule\Validator\Service\Exception\ServiceCreationException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\Stdlib\ArrayUtils;
use Psr\Container\ContainerInterface;

use function class_exists;
use function is_string;
use function sprintf;

/**
 * Base validator factory
 *
 * @internal
 */
abstract class AbstractValidatorFactory implements FactoryInterface
{
    public const string DEFAULT_OBJECTMANAGER_KEY = 'doctrine.entitymanager.orm_default';

    /** @var array */
    protected array $creationOptions = [];

    protected string $validatorClass;

    /**
     * @param array $options
     *
     * @throws ServiceCreationException
     */
    protected function getRepository(ContainerInterface $container, ?array $options = null): ObjectRepository
    {
        if (($options['object_repository'] ?? null) instanceof ObjectRepository) {
            return $options['object_repository'];
        }

        if (empty($options['target_class'])) {
            throw new ServiceCreationException(message: sprintf(
                "Option 'target_class' is missing when creating validator %s",
                self::class,
            ));
        }

        $objectManager = $this->getObjectManager(container: $container, options: $options);
        /** @var mixed $targetClassName */
        $targetClassName = $options['target_class'];

        if (!class_exists(class: \Jield\Stdlib\Value::string($targetClassName))) {
            throw new BadMethodCallException(message: sprintf('Class %s could not be found.', \Jield\Stdlib\Value::scalar($targetClassName)));
        }

        return $objectManager->getRepository(className: \Jield\Stdlib\Value::classString($targetClassName));
    }

    /** @param array $options */
    protected function getObjectManager(ContainerInterface $container, ?array $options = null): ObjectManager
    {
        /** @var mixed $objectManager */
        $objectManager = $options['object_manager'] ?? self::DEFAULT_OBJECTMANAGER_KEY;

        if (is_string(value: $objectManager)) {
            /** @var mixed $objectManager */
            $objectManager = $container->get($objectManager);
        }

        return \Jield\Stdlib\Value::instance($objectManager, \Doctrine\Persistence\ObjectManager::class);
    }

    /**
     * @param array $options
     *
     * @return array
     */
    protected function getFields(array $options): array
    {
        if (isset($options['fields'])) {
            return \Jield\Stdlib\Value::toArray($options['fields']);
        }

        return [];
    }

    /**
     * Helper to merge options array passed to `__invoke`
     * together with the options array created based on the above
     * helper methods.
     *
     * @param array $previousOptions
     * @param array $newOptions
     *
     * @return array
     */
    protected function merge(array $previousOptions, array $newOptions): array
    {
        return ArrayUtils::merge(a: $previousOptions, b: $newOptions, preserveNumericKeys: true);
    }
}
