<?php

declare(strict_types=1);

namespace DoctrineORMModule\Service;

use Doctrine\ORM\EntityManager;
use DoctrineModule\Form\Element\ObjectMultiCheckbox;
use Jield\Stdlib\Service;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

/**
 * Factory for {@see ObjectMultiCheckbox}
 */
final class ObjectMultiCheckboxFactory implements FactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): ObjectMultiCheckbox {
        $entityManager = Service::get($container, EntityManager::class);
        $element = new ObjectMultiCheckbox();

        $element->getProxy()->setObjectManager(objectManager: $entityManager);

        return $element;
    }
}
