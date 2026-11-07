<?php

declare(strict_types=1);

namespace Jield\Form\Annotation;

use Jield\Form\Factory;
use Jield\Form\FormElementManager;
use Jield\Form\InputFilterFactory;
use Jield\Stdlib\Service;
use Jield\Stdlib\Value;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

use function is_array;
use function is_subclass_of;
use function sprintf;

final class BuilderAbstractFactory implements AbstractFactoryInterface
{
    /** @var array<string, class-string<AbstractBuilder>> */
    private array $aliases = [
        'FormAnnotationBuilder' => AttributeBuilder::class,
        'FormAttributeBuilder' => AttributeBuilder::class,
    ];

    /**
     * @param  string $requestedName
     * @throws ServiceNotCreatedException For invalid listener configuration.
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \ReflectionException
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): AbstractBuilder
    {
        // resolve aliases used in laminas servicemanager
        if (isset($this->aliases[$requestedName])) {
            $requestedName = $this->aliases[$requestedName];
        }

        // setup a form factory which can use custom form elements
        $annotationBuilder = Value::instantiate($requestedName);
        $eventManager = Service::get($container, \Laminas\EventManager\EventManagerInterface::class, 'EventManager');
        Value::call($annotationBuilder, 'setEventManager', [$eventManager]);

        $this->injectFactory(Assert::isInstanceOf(Value::call($annotationBuilder, 'getFormFactory'), \Jield\Form\Factory::class), $container);

        $config = $this->marshalConfig($container);
        if (isset($config['preserve_defined_order'])) {
            Value::call($annotationBuilder, 'setPreserveDefinedOrder', [$config['preserve_defined_order']]);
        }

        $this->injectListeners($config, $eventManager, $container);

        return Assert::isInstanceOf($annotationBuilder, \Jield\Form\Annotation\AbstractBuilder::class);
    }

    /**
     * @param  string $requestedName
     */
    public function canCreate(ContainerInterface $container, $requestedName): bool
    {
        return isset($this->aliases[$requestedName]) || is_subclass_of($requestedName, AbstractBuilder::class);
    }

    /**
     * Marshal annotation builder configuration, if any.
     *
     * Looks for the `config` service in the container, returning an empty array
     * if not found.
     *
     * If found, checks for a `form_annotation_builder` entry, returning an empty
     * array if not found or not an array.
     *
     * Otherwise, returns the `form_annotation_builder` array.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    private function marshalConfig(ContainerInterface $container): array
    {
        if (!$container->has('config')) {
            return [];
        }

        /** @mago-var mixed $config */
        $config = $container->get('config');
        /** @mago-var mixed $config */
        $config = $config['form_annotation_builder'] ?? [];

        return is_array($config) ? $config : [];
    }

    /**
     * Inject event listeners from configuration, if any.
     *
     * Loops through the 'listeners' array, and:
     *
     * - attempts to fetch it from the container
     * - if the fetched instance is not a `ListenerAggregate`, raises an exception
     * - otherwise attaches it to the event manager
     *
     * @throws ServiceNotCreatedException If any listener is not an event listener aggregate.
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    private function injectListeners(array $config, EventManagerInterface $events, ContainerInterface $container): void
    {
        if (!isset($config['listeners'])) {
            return;
        }

        /** @mago-var mixed $listenerName */
        foreach (Assert::isIterable($config['listeners']) as $listenerName) {
            /** @mago-var mixed $listener */
            $listener = $container->get(Assert::string($listenerName));

            if (!$listener instanceof ListenerAggregateInterface) {
                throw new ServiceNotCreatedException(sprintf('Invalid event listener (%s) provided', Value::scalar($listenerName)));
            }

            $listener->attach($events);
        }
    }

    /**
     * Inject the annotation builder's factory instance with the FormElementManager.
     *
     * Also injects the factory with the InputFilterManager if present.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    private function injectFactory(Factory $factory, ContainerInterface $container): void
    {
        $factory->setFormElementManager(Service::get($container, FormElementManager::class));

        if ($container->has(InputFilterPluginManager::class)) {
            $inputFilters = Service::get($container, InputFilterPluginManager::class);
            $factory->setInputFilterFactory(InputFilterFactory::create($container));
        }
    }
}
