<?php

declare(strict_types=1);

namespace DoctrineModule\Validator\Service;

use BadMethodCallException;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use DoctrineModule\Validator\Service\Exception\ServiceCreationException;
use Jield\Stdlib\Value;
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

    protected array $creationOptions = [];

    protected string $validatorClass;

    /**
     * @param array<array-key, mixed>|null $options
     *
     * @throws ServiceCreationException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws BadMethodCallException
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
        /** @mago-var mixed $targetClassName */
        $targetClassName = $options['target_class'];

        if (!class_exists(class: \Webmozart\Assert\Assert::string($targetClassName))) {
            throw new BadMethodCallException(message: sprintf('Class %s could not be found.', Value::scalar($targetClassName)));
        }

        return $objectManager->getRepository(className: Value::classString($targetClassName));
    }

    /**
     * @param array<array-key, mixed>|null $options
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function getObjectManager(ContainerInterface $container, ?array $options = null): ObjectManager
    {
        /** @mago-var mixed $objectManager */
        $objectManager = $options['object_manager'] ?? self::DEFAULT_OBJECTMANAGER_KEY;

        if (is_string(value: $objectManager)) {
            /** @mago-var mixed $objectManager */
            $objectManager = $container->get($objectManager);
        }

        return \Webmozart\Assert\Assert::isInstanceOf($objectManager, \Doctrine\Persistence\ObjectManager::class);
    }

    protected function getFields(array $options): array
    {
        if (isset($options['fields'])) {
            return Value::toArray($options['fields']);
        }

        return [];
    }

    /**
     * Helper to merge options array passed to `__invoke`
     * together with the options array created based on the above
     * helper methods.
     */
    protected function merge(array $previousOptions, array $newOptions): array
    {
        return ArrayUtils::merge(a: $previousOptions, b: $newOptions, preserveNumericKeys: true);
    }
}
