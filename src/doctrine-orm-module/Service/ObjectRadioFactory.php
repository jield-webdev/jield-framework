<?php

declare(strict_types=1);

namespace DoctrineORMModule\Service;

use Doctrine\ORM\EntityManager;
use DoctrineModule\Form\Element\ObjectRadio;
use Jield\Stdlib\Service;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

final class ObjectRadioFactory implements FactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): \DoctrineModule\Form\Element\ObjectRadio {
        $entityManager = Service::get($container, EntityManager::class);
        $element = new ObjectRadio();

        $element->getProxy()->setObjectManager(objectManager: $entityManager);

        return $element;
    }
}
