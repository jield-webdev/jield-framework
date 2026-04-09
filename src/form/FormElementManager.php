<?php

declare(strict_types=1);

namespace Jield\Form;

use Jield\Stdlib\InitializableInterface;
use Laminas\Hydrator\HydratorInterface;
use Laminas\Hydrator\HydratorPluginManager;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\ServiceManager\AbstractPluginManager;
use Laminas\ServiceManager\Exception\InvalidServiceException;
use Psr\Container\ContainerInterface;
use function array_search;
use function array_unshift;
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
 */
class FormElementManager extends AbstractPluginManager
{
    /**
     * Aliases for default set of helpers
     *
     * @var array
     */
    protected array $aliases
        = [
            'button'         => Element\Button::class,
            'Button'         => Element\Button::class,
            'checkbox'       => Element\Checkbox::class,
            'Checkbox'       => Element\Checkbox::class,
            'collection'     => Element\Collection::class,
            'Collection'     => Element\Collection::class,
            'color'          => Element\Color::class,
            'Color'          => Element\Color::class,
            'csrf'           => Element\Csrf::class,
            'Csrf'           => Element\Csrf::class,
            'date'           => Element\Date::class,
            'Date'           => Element\Date::class,
            'dateselect'     => Element\DateSelect::class,
            'dateSelect'     => Element\DateSelect::class,
            'DateSelect'     => Element\DateSelect::class,
            'datetimelocal'  => Element\DateTimeLocal::class,
            'dateTimeLocal'  => Element\DateTimeLocal::class,
            'DateTimeLocal'  => Element\DateTimeLocal::class,
            'datetimeselect' => Element\DateTimeSelect::class,
            'dateTimeSelect' => Element\DateTimeSelect::class,
            'DateTimeSelect' => Element\DateTimeSelect::class,
            'element'        => Element::class,
            'Element'        => Element::class,
            'email'          => Element\Email::class,
            'Email'          => Element\Email::class,
            'fieldset'       => Fieldset::class,
            'Fieldset'       => Fieldset::class,
            'file'           => Element\File::class,
            'File'           => Element\File::class,
            'form'           => Form::class,
            'Form'           => Form::class,
            'hidden'         => Element\Hidden::class,
            'Hidden'         => Element\Hidden::class,
            'image'          => Element\Image::class,
            'Image'          => Element\Image::class,
            'month'          => Element\Month::class,
            'Month'          => Element\Month::class,
            'monthselect'    => Element\MonthSelect::class,
            'monthSelect'    => Element\MonthSelect::class,
            'MonthSelect'    => Element\MonthSelect::class,
            'multicheckbox'  => Element\MultiCheckbox::class,
            'multiCheckbox'  => Element\MultiCheckbox::class,
            'MultiCheckbox'  => Element\MultiCheckbox::class,
            'multiCheckBox'  => Element\MultiCheckbox::class,
            'MultiCheckBox'  => Element\MultiCheckbox::class,
            'number'         => Element\Number::class,
            'Number'         => Element\Number::class,
            'password'       => Element\Password::class,
            'Password'       => Element\Password::class,
            'radio'          => Element\Radio::class,
            'Radio'          => Element\Radio::class,
            'range'          => Element\Range::class,
            'Range'          => Element\Range::class,
            'search'         => Element\Search::class,
            'Search'         => Element\Search::class,
            'select'         => Element\Select::class,
            'Select'         => Element\Select::class,
            'submit'         => Element\Submit::class,
            'Submit'         => Element\Submit::class,
            'tel'            => Element\Tel::class,
            'Tel'            => Element\Tel::class,
            'text'           => Element\Text::class,
            'Text'           => Element\Text::class,
            'textarea'       => Element\Textarea::class,
            'Textarea'       => Element\Textarea::class,
            'time'           => Element\Time::class,
            'Time'           => Element\Time::class,
            'url'            => Element\Url::class,
            'Url'            => Element\Url::class,
            'week'           => Element\Week::class,
            'Week'           => Element\Week::class,
        ];

    /**
     * Factories for default set of helpers
     *
     * @var array
     */
    protected array $factories
        = [
            Element\Button::class         => ElementFactory::class,
            Element\Checkbox::class       => ElementFactory::class,
            Element\Collection::class     => ElementFactory::class,
            Element\Color::class          => ElementFactory::class,
            Element\Csrf::class           => ElementFactory::class,
            Element\Date::class           => ElementFactory::class,
            Element\DateSelect::class     => ElementFactory::class,
            Element\DateTimeLocal::class  => ElementFactory::class,
            Element\DateTimeSelect::class => ElementFactory::class,
            Element::class                => ElementFactory::class,
            Element\Email::class          => ElementFactory::class,
            Fieldset::class               => ElementFactory::class,
            Element\File::class           => ElementFactory::class,
            Form::class                   => ElementFactory::class,
            Element\Hidden::class         => ElementFactory::class,
            Element\Image::class          => ElementFactory::class,
            Element\Month::class          => ElementFactory::class,
            Element\MonthSelect::class    => ElementFactory::class,
            Element\MultiCheckbox::class  => ElementFactory::class,
            Element\Number::class         => ElementFactory::class,
            Element\Password::class       => ElementFactory::class,
            Element\Radio::class          => ElementFactory::class,
            Element\Range::class          => ElementFactory::class,
            Element\Search::class         => ElementFactory::class,
            Element\Select::class         => ElementFactory::class,
            Element\Submit::class         => ElementFactory::class,
            Element\Tel::class            => ElementFactory::class,
            Element\Text::class           => ElementFactory::class,
            Element\Textarea::class       => ElementFactory::class,
            Element\Time::class           => ElementFactory::class,
            Element\Url::class            => ElementFactory::class,
            Element\Week::class           => ElementFactory::class,
        ];

    /**
     * Don't share form elements by default (v3)
     *
     * @var bool
     */
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

        $factory = $instance->getFormFactory();
        $factory->setFormElementManager($this);

        if ($container->has(id: InputFilterPluginManager::class)) {
            $inputFilters = $container->get(InputFilterPluginManager::class);
            $factory->getInputFilterFactory()->setInputFilterManager($inputFilters);
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

        if (!$this->has(id: $class)) {
            $this->setFactory(name: $class, factory: ElementFactory::class);
        }

        if ($class === $name) {
            return;
        }

        $this->setAlias(alias: $name, target: $class);
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
                get_debug_type(value: $instance)
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
    public function configure(array $config): AbstractPluginManager
    {
        $firstInitializer = [$this, 'injectFactory'];
        $lastInitializer  = [$this, 'callElementInit'];

        foreach ([$firstInitializer, $lastInitializer] as $default) {
            if (false === ($index = array_search(needle: $default, haystack: $this->initializers))) {
                continue;
            }
            unset($this->initializers[$index]);
        }

        parent::configure(config: $config);

        array_unshift($this->initializers, $firstInitializer);
        $this->initializers[] = $lastInitializer;

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
     * @param string|class-string<T> $name Service name of plugin to retrieve.
     * @param null|array<mixed> $options Options to use when creating the instance.
     * @return ($name is class-string<ElementInterface> ? T : ElementInterface)
     */
    public function get(string $name, ?array $options = null): mixed
    {
        if (!$this->has(id: $name)) {
            if (!$this->autoAddInvokableClass || !class_exists(class: $name)) {
                throw new Exception\InvalidElementException(
                    message: sprintf(
                        'A plugin by the name "%s" was not found in the plugin manager %s',
                        $name,
                        static::class
                    )
                );
            }

            $this->setInvokableClass(name: $name, class: $name);
        }
        return parent::get($name, $options);
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

        if ($services && $services->has(HydratorPluginManager::class)) {
            $hydrators = $services->get(HydratorPluginManager::class);
            if ($hydrators->has($hydratorName)) {
                return $hydrators->get($hydratorName);
            }
        }

        if ($services && $services->has($hydratorName)) {
            return $services->get($hydratorName);
        }

        if (!class_exists(class: $hydratorName)) {
            throw new Exception\DomainException(message: sprintf(
                'Expects string hydrator name to be a valid class name; received "%s"',
                $hydratorName
            ));
        }

        return new $hydratorName();
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

        if ($services && $services->has($factoryName)) {
            return $services->get($factoryName);
        }

        if (!class_exists(class: $factoryName)) {
            throw new Exception\DomainException(message: sprintf(
                'Expects string factory name to be a valid class name; received "%s"',
                $factoryName
            ));
        }

        return new $factoryName();
    }
}
