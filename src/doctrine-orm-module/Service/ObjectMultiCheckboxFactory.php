<?php

declare(strict_types=1);

namespace DoctrineORMModule\Service;

use Doctrine\ORM\EntityManager;
use DoctrineModule\Form\Element\ObjectMultiCheckbox;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

/**
 * Factory for {@see ObjectMultiCheckbox}
 */
final class ObjectMultiCheckboxFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @param string $requestedName
     *
     * @return ObjectMultiCheckbox
     */
    public function __invoke(ContainerInterface $container, string $requestedName, array|null $options = null): ObjectMultiCheckbox
    {
        $entityManager = $container->get(EntityManager::class);
        $element       = new ObjectMultiCheckbox();

        $element->getProxy()->setObjectManager(objectManager: $entityManager);

        return $element;
    }
}
