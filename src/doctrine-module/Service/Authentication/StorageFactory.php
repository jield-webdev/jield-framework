<?php

declare(strict_types=1);

namespace DoctrineModule\Service\Authentication;

use DoctrineModule\Authentication\Storage\ObjectRepository;
use DoctrineModule\Options\Authentication;
use DoctrineModule\Service\AbstractFactory;
use Psr\Container\ContainerInterface;
use RuntimeException;

use function is_string;
use function sprintf;

/**
 * Factory to create authentication storage object.
 */
final class StorageFactory extends AbstractFactory
{
    /**
     * {@inheritDoc}
     * @throws RuntimeException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \ReflectionException
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): ObjectRepository
    {
        $containerOptions = $this->getOptions(container: $container, key: 'authentication');

        if (!$containerOptions instanceof Authentication) {
            throw new RuntimeException(message: sprintf(
                'Invalid options received, expected %s, got %s.',
                Authentication::class,
                $containerOptions::class,
            ));
        }

        /** @mago-var mixed $objectManager */
        $objectManager = $containerOptions->getObjectManager();
        if (is_string(value: $objectManager)) {
            $containerOptions->setObjectManager(objectManager: \Jield\Stdlib\Value::instanceOrString($container->get($objectManager), \Doctrine\Persistence\ObjectManager::class));
        }

        $storage = $containerOptions->getStorage();
        if (is_string(value: $storage)) {
            $containerOptions->setStorage(storage: \Jield\Stdlib\Value::instanceOrString($container->get($storage), \Laminas\Authentication\Storage\StorageInterface::class));
        }

        return new ObjectRepository(options: $containerOptions);
    }

    public function getOptionsClass(): string
    {
        return Authentication::class;
    }
}
