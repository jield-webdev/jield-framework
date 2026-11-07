<?php

declare(strict_types=1);

namespace Jield\Form;

use Jield\Stdlib\Service;
use Jield\Stdlib\Value;
use Laminas\Hydrator\HydratorInterface;
use Laminas\Hydrator\HydratorPluginManager;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\ServiceManager\AbstractPluginManager;
use Laminas\ServiceManager\Exception\InvalidServiceException;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\InitializableInterface;
use Psr\Container\ContainerInterface;

use function class_exists;
use function get_debug_type;
use function method_exists;
use function sprintf;

/**
 * jield-servicemanager v3-compatible plugin manager implementation for form elements.
 *
 * Enforces that elements retrieved are instances of ElementInterface.
 *
 * @final
 * @extends AbstractPluginManager<ElementInterface>
 * @psalm-import-type FactoriesConfiguration from ServiceManager
 * @psalm-import-type ServiceManagerConfiguration from ServiceManager
 */
class FormElementManager extends AbstractPluginManager
{
    /**
     * Aliases for default set of helpers
     *
     * @var array<array-key, string>
     */
    protected array $aliases = [
        'button' => Element\Button::class,
        'Button' => Element\Button::class,
        'checkbox' => Element\Checkbox::class,
        'Checkbox' => Element\Checkbox::class,
        'collection' => Element\Collection::class,
        'Collection' => Element\Collection::class,
        'color' => Element\Color::class,
        'Color' => Element\Color::class,
        'csrf' => Element\Csrf::class,
        'Csrf' => Element\Csrf::class,
        'date' => Element\Date::class,
        'Date' => Element\Date::class,
        'dateselect' => Element\DateSelect::class,
        'dateSelect' => Element\DateSelect::class,
        'DateSelect' => Element\DateSelect::class,
        'datetimelocal' => Element\DateTimeLocal::class,
        'dateTimeLocal' => Element\DateTimeLocal::class,
        'DateTimeLocal' => Element\DateTimeLocal::class,
        'datetimeselect' => Element\DateTimeSelect::class,
        'dateTimeSelect' => Element\DateTimeSelect::class,
        'DateTimeSelect' => Element\DateTimeSelect::class,
        'element' => Element::class,
        'Element' => Element::class,
        'email' => Element\Email::class,
        'Email' => Element\Email::class,
        'fieldset' => Fieldset::class,
        'Fieldset' => Fieldset::class,
        'file' => Element\File::class,
        'File' => Element\File::class,
        'form' => Form::class,
        'Form' => Form::class,
        'hidden' => Element\Hidden::class,
        'Hidden' => Element\Hidden::class,
        'image' => Element\Image::class,
        'Image' => Element\Image::class,
        'month' => Element\Month::class,
        'Month' => Element\Month::class,
        'monthselect' => Element\MonthSelect::class,
        'monthSelect' => Element\MonthSelect::class,
        'MonthSelect' => Element\MonthSelect::class,
        'multicheckbox' => Element\MultiCheckbox::class,
        'multiCheckbox' => Element\MultiCheckbox::class,
        'MultiCheckbox' => Element\MultiCheckbox::class,
        'multiCheckBox' => Element\MultiCheckbox::class,
        'MultiCheckBox' => Element\MultiCheckbox::class,
        'number' => Element\Number::class,
        'Number' => Element\Number::class,
        'password' => Element\Password::class,
        'Password' => Element\Password::class,
        'radio' => Element\Radio::class,
        'Radio' => Element\Radio::class,
        'range' => Element\Range::class,
        'Range' => Element\Range::class,
        'search' => Element\Search::class,
        'Search' => Element\Search::class,
        'select' => Element\Select::class,
        'Select' => Element\Select::class,
        'submit' => Element\Submit::class,
        'Submit' => Element\Submit::class,
        'tel' => Element\Tel::class,
        'Tel' => Element\Tel::class,
        'text' => Element\Text::class,
        'Text' => Element\Text::class,
        'textarea' => Element\Textarea::class,
        'Textarea' => Element\Textarea::class,
        'time' => Element\Time::class,
        'Time' => Element\Time::class,
        'url' => Element\Url::class,
        'Url' => Element\Url::class,
        'week' => Element\Week::class,
        'Week' => Element\Week::class,
    ];

    /**
     * Factories for default set of helpers
     *
     * @var FactoriesConfiguration
     */
    protected array $factories = [
        Element\Button::class => ElementFactory::class,
        Element\Checkbox::class => ElementFactory::class,
        Element\Collection::class => ElementFactory::class,
        Element\Color::class => ElementFactory::class,
        Element\Csrf::class => ElementFactory::class,
        Element\Date::class => ElementFactory::class,
        Element\DateSelect::class => ElementFactory::class,
        Element\DateTimeLocal::class => ElementFactory::class,
        Element\DateTimeSelect::class => ElementFactory::class,
        Element::class => ElementFactory::class,
        Element\Email::class => ElementFactory::class,
        Fieldset::class => ElementFactory::class,
        Element\File::class => ElementFactory::class,
        Form::class => ElementFactory::class,
        Element\Hidden::class => ElementFactory::class,
        Element\Image::class => ElementFactory::class,
        Element\Month::class => ElementFactory::class,
        Element\MonthSelect::class => ElementFactory::class,
        Element\MultiCheckbox::class => ElementFactory::class,
        Element\Number::class => ElementFactory::class,
        Element\Password::class => ElementFactory::class,
        Element\Radio::class => ElementFactory::class,
        Element\Range::class => ElementFactory::class,
        Element\Search::class => ElementFactory::class,
        Element\Select::class => ElementFactory::class,
        Element\Submit::class => ElementFactory::class,
        Element\Tel::class => ElementFactory::class,
        Element\Text::class => ElementFactory::class,
        Element\Textarea::class => ElementFactory::class,
        Element\Time::class => ElementFactory::class,
        Element\Url::class => ElementFactory::class,
        Element\Week::class => ElementFactory::class,
    ];

    public function __construct(
        /**
         * Don't share form elements by default (v3)
         */
        protected readonly ContainerInterface $creationContext,
        array $config = [],
    ) {
        /** @var ServiceManagerConfiguration $managerConfiguration */
        $managerConfiguration = $config;
        parent::__construct($this->creationContext, $managerConfiguration);
    }

    protected bool $sharedByDefault = false;

    /**
     * Interface all plugins managed by this class must implement.
     *
     * @var class-string
     */
    protected string $instanceOf = ElementInterface::class;

    /**
     * Inject the factory to any element that implements FormFactoryAwareInterface
     *
     * @param mixed $instance Instance to inspect and optionally inject.
     */
    public function injectFactory(ContainerInterface $container, mixed $instance): void
    {
        if (!$instance instanceof FieldsetInterface) {
            return;
        }

        if (!method_exists(object_or_class: $instance, method: 'getFormFactory')) {
            return;
        }

        /** @mago-var mixed $factory */
        $factory = $instance->getFormFactory();
        Value::call($factory, 'setFormElementManager', [$this]);

        if ($container->has(InputFilterPluginManager::class)) {
            $inputFilters = Service::get($container, InputFilterPluginManager::class);
            Value::call($factory, 'setInputFilterFactory', [\Jield\Form\InputFilterFactory::create($container)]);
        }
    }

    /**
     * Call init() on any element that implements InitializableInterface
     *
     * @param mixed $instance Instance to inspect and optionally initialize.
     */
    public function callElementInit(ContainerInterface $container, mixed $instance): void
    {
        if ($instance instanceof InitializableInterface) {
            $instance->init();
        }
    }

    /**
     * Override setInvokableClass
     *
     * Overrides setInvokableClass to:
     *
     * - add a factory mapping $invokableClass to ElementFactory::class
     * - alias $name to $invokableClass
     *
     * @param string $name
     * @param string|null $class
     */
    public function setInvokableClass(string $name, ?string $class = null): void
    {
        $class = $class ?: $name;

        if (!$this->has($class)) {
            $this->configure(['factories' => [$class => ElementFactory::class]]);
        }

        if ($class === $name) {
            return;
        }

        $this->configure(['aliases' => [$name => $class]]);
    }

    /**
     * Validate the plugin is of the expected type (v3).
     *
     * Validates against `$instanceOf`.
     *
     * @param mixed $instance
     * @throws InvalidServiceException
     * @psalm-assert ElementInterface $instance
     */
    public function validate(mixed $instance): void
    {
        if (!$instance instanceof $this->instanceOf) {
            throw new InvalidServiceException(message: sprintf(
                '%s can only create instances of %s; %s is invalid',
                static::class,
                $this->instanceOf,
                get_debug_type(value: $instance),
            ));
        }
    }

    /**
     * Overrides parent::configure in order to ensure default initializers are in expected positions.
     *
     * Always pushes `injectFactory` to top of initializer stack, and
     * `callElementInit` to the bottom.
     *
     * @inheritDoc
     */
    public function configure(array $config): static
    {
        $config['initializers'] = array_merge(
            [$this->injectFactory(...)],
            $config['initializers'] ?? [],
            [$this->callElementInit(...)],
        );
        parent::configure($config);
        return $this;
    }

    /**
     * Retrieve a service from the manager by name
     *
     * Allows passing an array of options to use when creating the instance.
     * createFromInvokable() will use these and pass them to the instance
     * constructor if not null and a non-empty array.
     *
     * @template T of ElementInterface
     * @param string|class-string<T> $id Service name of plugin to retrieve.
     * @param null|array<mixed> $options Options to use when creating the instance.
     * @return ($id is class-string<ElementInterface> ? T : ElementInterface)
     */
    public function get(string $id, ?array $options = null): mixed
    {
        if (!$this->has($id)) {
            if (!$this->autoAddInvokableClass || !class_exists(class: $id)) {
                throw new Exception\InvalidElementException(message: sprintf(
                    'A plugin by the name "%s" was not found in the plugin manager %s',
                    $id,
                    static::class,
                ));
            }

            $this->setInvokableClass(name: $id, class: $id);
        }
        /** @var ($id is class-string<ElementInterface> ? T : ElementInterface) $element */
        $element = $options === null ? parent::get($id) : parent::build($id, $options);
        return $element;
    }

    /**
     * Try to pull hydrator from the creation context, or instantiates it from its name
     *
     * @param string|class-string<HydratorInterface> $hydratorName
     * @return mixed
     * @psalm-return ($hydratorName is class-string<HydratorInterface> ? HydratorInterface : mixed)
     * @throws Exception\DomainException
     */
    public function getHydratorFromName(string $hydratorName): mixed
    {
        $services = $this->creationContext;

        if ($services->has(HydratorPluginManager::class)) {
            $hydrators = Service::get($services, HydratorPluginManager::class);
            if ($hydrators->has($hydratorName)) {
                return $hydrators->get($hydratorName);
            }
        }

        if ($services->has($hydratorName)) {
            return $services->get($hydratorName);
        }

        if (!class_exists(class: $hydratorName)) {
            throw new Exception\DomainException(message: sprintf(
                'Expects string hydrator name to be a valid class name; received "%s"',
                $hydratorName,
            ));
        }

        return Value::instantiate($hydratorName);
    }

    /**
     * Try to pull factory from the creation context, or instantiates it from its name
     *
     * @return mixed
     * @throws Exception\DomainException
     */
    public function getFactoryFromName(string $factoryName): mixed
    {
        $services = $this->creationContext;

        if ($services->has($factoryName)) {
            return $services->get($factoryName);
        }

        if (!class_exists(class: $factoryName)) {
            throw new Exception\DomainException(message: sprintf(
                'Expects string factory name to be a valid class name; received "%s"',
                $factoryName,
            ));
        }

        return Value::instantiate($factoryName);
    }
}
