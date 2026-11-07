<?php

declare(strict_types=1);

namespace DoctrineORMModule\Service;

use Doctrine\ORM\Tools\ResolveTargetEntityListener;
use DoctrineModule\Service\AbstractFactory;
use DoctrineORMModule\Options\EntityResolver;
use Jield\Stdlib\Value;
use Psr\Container\ContainerInterface;

use function assert;

final class EntityResolverFactory extends AbstractFactory
{
    /**
     * {@inheritDoc}
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): mixed
    {
        $options = $this->getOptions(container: $container, key: 'entity_resolver');
        assert(assertion: $options instanceof EntityResolver);
        $eventManager = $container->get($options->getEventManager());
        $resolvers = $options->getResolvers();

        $targetEntityListener = new ResolveTargetEntityListener();

        foreach ($resolvers as $oldEntity => $newEntity) {
            $targetEntityListener->addResolveTargetEntity(
                originalEntity: Value::string($oldEntity),
                newEntity: Value::string($newEntity),
                mapping: [],
            );
        }

        Value::call($eventManager, 'addEventSubscriber', [$targetEntityListener]);

        return $eventManager;
    }

    /**
     * Get the class name of the options associated with this factory.
     */
    public function getOptionsClass(): string
    {
        return EntityResolver::class;
    }
}
