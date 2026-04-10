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
    protected function getRepository(ContainerInterface $container, array|null $options = null): ObjectRepository
    {
        if (empty($options['target_class'])) {
            throw new ServiceCreationException(message: sprintf(
                "Option 'target_class' is missing when creating validator %s",
                self::class,
            ));
        }

        $objectManager   = $this->getObjectManager(container: $container, options: $options);
        $targetClassName = $options['target_class'];

        if (! class_exists(class: $targetClassName)) {
            throw new BadMethodCallException(message: sprintf('Class %s could not be found.', $targetClassName));
        }

        return $objectManager->getRepository(className: $targetClassName);
    }

    /** @param array $options */
    protected function getObjectManager(ContainerInterface $container, array|null $options = null): ObjectManager
    {
        $objectManager = $options['object_manager'] ?? self::DEFAULT_OBJECTMANAGER_KEY;

        if (is_string(value: $objectManager)) {
            $objectManager = $container->get($objectManager);
        }

        return $objectManager;
    }

    /**
     * @param array $options
     *
     * @return array
     */
    protected function getFields(array $options): array
    {
        if (isset($options['fields'])) {
            return (array) $options['fields'];
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
