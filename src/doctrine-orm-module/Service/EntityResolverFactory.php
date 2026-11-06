<?php

declare(strict_types=1);

namespace DoctrineORMModule\Service;

use Doctrine\ORM\Tools\ResolveTargetEntityListener;
use DoctrineModule\Service\AbstractFactory;
use DoctrineORMModule\Options\EntityResolver;
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
        /** @var mixed $eventManager */
        $eventManager = $container->get($options->getEventManager());
        $resolvers = $options->getResolvers();

        $targetEntityListener = new ResolveTargetEntityListener();

        /** @var mixed $newEntity */
        foreach ($resolvers as $oldEntity => $newEntity) {
            $targetEntityListener->addResolveTargetEntity(
                originalEntity: \Jield\Stdlib\Value::string($oldEntity),
                newEntity: \Jield\Stdlib\Value::string($newEntity),
                mapping: [],
            );
        }

        \Jield\Stdlib\Value::call($eventManager, 'addEventSubscriber', [$targetEntityListener]);

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
