<?php

declare(strict_types=1);

namespace DoctrineModule\Service\Authentication;

use Doctrine\Persistence\ObjectManager;
use DoctrineModule\Authentication\Adapter\ObjectRepository;
use DoctrineModule\Options\Authentication;
use DoctrineModule\Service\AbstractFactory;
use Jield\Stdlib\Value;
use Psr\Container\ContainerInterface;
use RuntimeException;

use function is_string;
use function sprintf;

/**
 * Factory to create authentication adapter object.
 */
final class AdapterFactory extends AbstractFactory
{
    /**
     * {@inheritDoc}
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

        /** @var mixed $objectManager */
        $objectManager = $containerOptions->getObjectManager();
        if (is_string(value: $objectManager)) {
            $containerOptions->setObjectManager(objectManager: Value::instanceOrString(
                $container->get($objectManager),
                ObjectManager::class,
            ));
        }

        return new ObjectRepository(options: $containerOptions);
    }

    public function getOptionsClass(): string
    {
        return Authentication::class;
    }
}
