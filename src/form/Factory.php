<?php

declare(strict_types=1);

namespace Jield\Form;

use ArrayAccess;
use Jield\Stdlib\Value;
use Laminas\Hydrator;
use Laminas\Hydrator\HydratorInterface;
use Laminas\InputFilter\Factory as InputFilterFactory;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\ArrayUtils;
use ReflectionClass;
use Traversable;
use Webmozart\Assert\Assert;

use function assert;
use function class_exists;
use function is_a;
use function is_array;
use function is_iterable;
use function is_string;
use function method_exists;
use function sprintf;

/** @final */
// Preserve the existing public API and the state and operations that implement it.
// @mago-expect lint:cyclomatic-complexity(1)
// @mago-expect lint:kan-defect(1)
// @mago-expect lint:too-many-methods(1)
class Factory
{
    protected ?InputFilterFactory $inputFilterFactory = null;

    protected ?FormElementManager $formElementManager = null;

    public function __construct(
        ?FormElementManager $formElementManager = null,
        ?InputFilterFactory $inputFilterFactory = null,
    ) {
        if ($formElementManager) {
            $this->setFormElementManager(formElementManager: $formElementManager);
        }

        if ($inputFilterFactory) {
            $this->setInputFilterFactory(inputFilterFactory: $inputFilterFactory);
        }
    }

    /**
     * Set input filter factory to use when creating forms
     */
    public function setInputFilterFactory(InputFilterFactory $inputFilterFactory): static
    {
        $this->inputFilterFactory = $inputFilterFactory;
        return $this;
    }

    /**
     * Get current input filter factory
     *
     * If none provided, uses an unconfigured instance.
     */
    public function getInputFilterFactory(): InputFilterFactory
    {
        if (null === $this->inputFilterFactory) {
            $this->inputFilterFactory = \Jield\Form\InputFilterFactory::create();
        }
        return $this->inputFilterFactory;
    }

    /**
     * Set the form element manager
     */
    public function setFormElementManager(FormElementManager $formElementManager): static
    {
        $this->formElementManager = $formElementManager;
        return $this;
    }

    /**
     * Get form element manager
     */
    public function getFormElementManager(): FormElementManager
    {
        if ($this->formElementManager === null) {
            $this->formElementManager = new FormElementManager(creationContext: new ServiceManager());
        }

        return $this->formElementManager;
    }

    /**
     * Create an element, fieldset, or form
     *
     * Introspects the 'type' key of the provided $spec, and determines what
     * type is being requested; if none is provided, assumes the spec
     * represents simply an element.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws Exception\ExceptionInterface
     * @throws \Laminas\InputFilter\Exception\RuntimeException
     * @throws \ReflectionException
     */
    public function create(Traversable|ArrayAccess|array $spec): ElementInterface
    {
        $spec = $this->validateSpecification(spec: $spec, method: __METHOD__);
        /** @mago-var mixed $type */
        $type = $spec['type'] ?? Element::class;

        $element = $this->getFormElementManager()->get(Assert::string($type));

        if ($element instanceof FormInterface) {
            return $this->configureForm(form: $element, spec: $spec);
        }

        if ($element instanceof FieldsetInterface) {
            return $this->configureFieldset(fieldset: $element, spec: $spec);
        }

        return $this->configureElement(element: $element, spec: $spec);
    }

    /**
     * Create an element
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws Exception\ExceptionInterface
     * @throws \Laminas\InputFilter\Exception\RuntimeException
     * @throws \ReflectionException
     */
    public function createElement(array $spec): ElementInterface
    {
        $spec['type'] ??= Element::class;

        return $this->create(spec: $spec);
    }

    /**
     * Create a fieldset
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws Exception\ExceptionInterface
     * @throws \Laminas\InputFilter\Exception\RuntimeException
     * @throws \ReflectionException
     */
    public function createFieldset(array $spec): FieldsetInterface
    {
        $spec['type'] ??= Fieldset::class;

        return Assert::isInstanceOf($this->create(spec: $spec), \Jield\Form\FieldsetInterface::class);
    }

    /**
     * Create a form
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws Exception\ExceptionInterface
     * @throws \Laminas\InputFilter\Exception\RuntimeException
     * @throws \ReflectionException
     */
    public function createForm(array $spec): FormInterface
    {
        $spec['type'] ??= Form::class;

        return Assert::isInstanceOf($this->create(spec: $spec), \Jield\Form\FormInterface::class);
    }

    /**
     * Configure an element based on the provided specification
     *
     * Specification can contain any of the following:
     * - type: the Element class to use; defaults to \Jield\Form\Element
     * - name: what name to provide the element, if any
     * - options: an array or Traversable object of element options
     * - attributes: an array or Traversable object of element attributes to assign
     *
     * @throws Exception\DomainException
     */
    public function configureElement(ElementInterface $element, Traversable|ArrayAccess|array $spec): ElementInterface
    {
        $spec = $this->validateSpecification(spec: $spec, method: __METHOD__);

        /** @mago-var mixed $name */
        $name = $spec['name'] ?? null;
        /** @mago-var mixed $options */
        $options = $spec['options'] ?? null;
        /** @mago-var mixed $attributes */
        $attributes = $spec['attributes'] ?? null;

        if ($name !== null && $name !== '') {
            $element->setName(name: Assert::string($name));
        }

        if (is_iterable(value: $options)) {
            $element->setOptions(options: $options);
        }

        if (is_iterable(value: $attributes)) {
            $element->setAttributes(arrayOrTraversable: Value::scalarMap(
                is_array($attributes) ? $attributes : iterator_to_array($attributes),
            ));
        }

        return $element;
    }

    /**
     * Configure a fieldset based on the provided specification
     *
     * Specification can contain any of the following:
     * - type: the Fieldset class to use; defaults to \Jield\Form\Fieldset
     * - name: what name to provide the fieldset, if any
     * - options: an array, Traversable, or ArrayAccess object of element options
     * - attributes: an array, Traversable, or ArrayAccess object of element
     *   attributes to assign
     * - elements: an array or Traversable object where each entry is an array
     *   or ArrayAccess object containing the keys:
     *   - flags: (optional) array of flags to pass to FieldsetInterface::add()
     *   - spec: the actual element specification, per {@link configureElement()}
     *
     * @throws Exception\ExceptionInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \ReflectionException
     * @throws \Laminas\InputFilter\Exception\RuntimeException
     */
    public function configureFieldset(
        FieldsetInterface $fieldset,
        Traversable|ArrayAccess|array $spec,
    ): FieldsetInterface {
        $spec = $this->validateSpecification(spec: $spec, method: __METHOD__);
        $fieldset = $this->configureElement(element: $fieldset, spec: $spec);
        assert(
            assertion: $fieldset instanceof FieldsetInterface,
            description: 'Expected $fieldset instanceof FieldsetInterface.',
        );

        if (($spec['object'] ?? null) !== null) {
            $this->prepareAndInjectObject(
                objectName: Assert::string($spec['object']),
                fieldset: $fieldset,
                method: __METHOD__,
            );
        }

        if (($spec['hydrator'] ?? null) !== null) {
            /** @mago-var mixed $hydrator */
            $hydrator = $spec['hydrator'];
            $this->prepareAndInjectHydrator(
                hydratorOrName: Assert::notNull(Value::instanceOrArrayOrString($hydrator, HydratorInterface::class)),
                fieldset: $fieldset,
                method: __METHOD__,
            );
        }

        if (($spec['elements'] ?? null) !== null) {
            $this->prepareAndInjectElements(
                elements: Assert::isIterable($spec['elements']),
                fieldset: $fieldset,
                method: __METHOD__,
            );
        }

        if (($spec['fieldsets'] ?? null) !== null) {
            $this->prepareAndInjectFieldsets(
                fieldsets: Assert::isIterable($spec['fieldsets']),
                masterFieldset: $fieldset,
                method: __METHOD__,
            );
        }

        /** @mago-var mixed $factory */
        $factory = $spec['factory'] ?? $this;
        $this->prepareAndInjectFactory(
            factoryOrName: Assert::notNull(Value::instanceOrArrayOrString($factory, self::class)),
            fieldset: $fieldset,
            method: __METHOD__,
        );

        return $fieldset;
    }

    /**
     * Configure a form based on the provided specification
     *
     * Specification follows that of {@link configureFieldset()}, and adds the
     * following keys:
     *
     * - input_filter: input filter instance, named input filter class, or
     *   array specification for the input filter factory
     * - hydrator: hydrator instance or named hydrator class
     * @throws \Laminas\InputFilter\Exception\RuntimeException
     * @throws Exception\ExceptionInterface
     * @throws \ReflectionException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function configureForm(FormInterface $form, Traversable|ArrayAccess|array $spec): FormInterface
    {
        $spec = $this->validateSpecification(spec: $spec, method: __METHOD__);
        $form = $this->configureFieldset(fieldset: $form, spec: $spec);
        assert(assertion: $form instanceof FormInterface, description: 'Expected $form instanceof FormInterface.');

        if (($spec['input_filter'] ?? null) !== null) {
            /** @mago-var mixed $inputFilter */
            $inputFilter = $spec['input_filter'];
            $this->prepareAndInjectInputFilter(
                spec: is_string($inputFilter) || $inputFilter instanceof InputFilterInterface
                    ? $inputFilter
                    : Assert::isIterable($inputFilter),
                form: $form,
                method: __METHOD__,
            );
        }

        if (($spec['validation_group'] ?? null) !== null) {
            $form->setValidationGroup(group: Assert::isArray($spec['validation_group']));
        }

        return $form;
    }

    /**
     * Validate a provided specification
     *
     * Ensures we have an array, Traversable, or ArrayAccess object, and returns it.
     *
     * @param  string $method Method invoking the validator
     * @throws Exception\InvalidArgumentException For invalid $spec.
     */
    protected function validateSpecification(Traversable|ArrayAccess|array $spec, string $method): ArrayAccess|array
    {
        if (is_array(value: $spec)) {
            return $spec;
        }

        if ($spec instanceof Traversable) {
            return ArrayUtils::iteratorToArray(iterator: $spec);
        }

        return $spec;
    }

    /**
     * Takes a list of element specifications, creates the elements, and injects them into the provided fieldset
     *
     * @param  string $method Method invoking this one (for exception messages)
     * @throws Exception\ExceptionInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Laminas\InputFilter\Exception\RuntimeException
     * @throws \ReflectionException
     */
    protected function prepareAndInjectElements(
        Traversable|ArrayAccess|array $elements,
        FieldsetInterface $fieldset,
        string $method,
    ): void {
        $elements = $this->validateSpecification(spec: $elements, method: $method);

        /** @mago-var mixed $elementSpecification */
        foreach (Assert::isIterable($elements) as $elementSpecification) {
            if (null === $elementSpecification) {
                continue;
            }

            /** @mago-var mixed $flags */
            $flags = $elementSpecification['flags'] ?? [];
            /** @mago-var mixed $spec */
            $spec = $elementSpecification['spec'] ?? [];

            if (($spec['type'] ?? null) === null) {
                Value::setPath($spec, ['type'], Element::class);
            }

            $element = $this->create(spec: $spec);
            $fieldset->add(elementOrFieldset: $element, flags: Assert::isArray($flags));
        }
    }

    /**
     * Takes a list of fieldset specifications, creates the fieldsets, and injects them into the master fieldset
     *
     * @param  string $method Method invoking this one (for exception messages)
     * @throws Exception\ExceptionInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Laminas\InputFilter\Exception\RuntimeException
     * @throws \ReflectionException
     */
    public function prepareAndInjectFieldsets(
        Traversable|ArrayAccess|array $fieldsets,
        FieldsetInterface $masterFieldset,
        string $method,
    ): void {
        $fieldsets = $this->validateSpecification(spec: $fieldsets, method: $method);

        /** @mago-var mixed $fieldsetSpecification */
        foreach (Assert::isIterable($fieldsets) as $fieldsetSpecification) {
            /** @mago-var mixed $flags */
            $flags = $fieldsetSpecification['flags'] ?? [];
            /** @mago-var mixed $spec */
            $spec = $fieldsetSpecification['spec'] ?? [];

            $fieldset = $this->createFieldset(spec: Assert::isArray($spec));
            $masterFieldset->add(elementOrFieldset: $fieldset, flags: Assert::isArray($flags));
        }
    }

    /**
     * Prepare and inject an object
     *
     * Takes a string indicating a class name, instantiates the class
     * by that name, and injects the class instance as the bound object.
     *
     * @throws Exception\DomainException
     * @throws \ReflectionException
     */
    protected function prepareAndInjectObject(string $objectName, FieldsetInterface $fieldset, string $method): void
    {
        if (!class_exists(class: $objectName)) {
            throw new Exception\DomainException(message: sprintf(
                '%s expects string class name to be a valid class name; received "%s"',
                $method,
                $objectName,
            ));
        }

        $fieldset->setObject(object: Value::instantiate($objectName));
    }

    /**
     * Prepare and inject a named hydrator
     *
     * Takes a string indicating a hydrator class name (or a concrete instance), try first to instantiates the class
     * by pulling it from service manager, and injects the hydrator instance into the form.
     *
     * @throws Exception\DomainException If $hydratorOrName is not a string, does not resolve to a known class, or
     *                                   the class does not implement Hydrator\HydratorInterface.
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \ReflectionException
     */
    protected function prepareAndInjectHydrator(
        HydratorInterface|array|string $hydratorOrName,
        FieldsetInterface $fieldset,
        string $method,
    ): void {
        if ($hydratorOrName instanceof HydratorInterface) {
            $fieldset->setHydrator(hydrator: $hydratorOrName);
            return;
        }

        // These alternatives are mutually exclusive; the following code consumes the selected state.
        // @mago-expect lint:no-else-clause(1)
        if (is_array(value: $hydratorOrName)) {
            if (($hydratorOrName['type'] ?? null) === null) {
                throw new Exception\DomainException(message: sprintf(
                    '%s expects array specification to have a type value',
                    $method,
                ));
            }
            /** @mago-var mixed $hydratorOptions */
            $hydratorOptions = $hydratorOrName['options'] ?? [];
            /** @mago-var mixed $hydratorOrName */
            $hydratorOrName = $hydratorOrName['type'];
        } else {
            $hydratorOptions = [];
        }

        if (is_string(value: $hydratorOrName)) {
            /** @mago-var mixed $hydrator */
            $hydrator = $this->getFormElementManager()->getHydratorFromName(hydratorName: $hydratorOrName);
        }

        if (($hydrator ?? null) === null || !$hydrator instanceof HydratorInterface) {
            throw new Exception\DomainException(message: sprintf(
                '%s expects a valid implementation of Laminas\Hydrator\HydratorInterface; received "%s"',
                $method,
                Value::scalar($hydratorOrName),
            ));
        }

        if (Value::truthy($hydratorOptions) && $hydrator instanceof Hydrator\HydratorOptionsInterface) {
            $hydrator->setOptions(options: Assert::isArray($hydratorOptions));
        }

        $fieldset->setHydrator(hydrator: $hydrator);
    }

    /**
     * Prepare and inject a named factory
     *
     * Takes a string indicating a factory class name (or a concrete instance), try first to instantiates the class
     * by pulling it from service manager, and injects the factory instance into the fieldset.
     *
     * @throws Exception\DomainException If $factoryOrName is not a string, does not resolve to a known class, or
     *                                   the class does not extend Form\Factory.
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \ReflectionException
     */
    protected function prepareAndInjectFactory(
        Factory|array|string $factoryOrName,
        FieldsetInterface $fieldset,
        string $method,
    ): void {
        if (is_array(value: $factoryOrName)) {
            if (($factoryOrName['type'] ?? null) === null) {
                throw new Exception\DomainException(message: sprintf(
                    '%s expects array specification to have a type value',
                    $method,
                ));
            }
            /** @mago-var mixed $factoryOrName */
            $factoryOrName = $factoryOrName['type'];
        }

        if (is_string(value: $factoryOrName)) {
            /** @mago-var mixed $factoryOrName */
            $factoryOrName = $this->getFormElementManager()->getFactoryFromName(factoryName: $factoryOrName);
        }

        if (!$factoryOrName instanceof Factory) {
            throw new Exception\DomainException(message: sprintf(
                '%s expects a valid extension of Jield\Form\Factory; received "%s"',
                $method,
                Value::scalar($factoryOrName),
            ));
        }

        $fieldset->setFormFactory(formFactory: $factoryOrName);
    }

    /**
     * Prepare an input filter instance and inject in the provided form
     *
     * If the input filter specified is a string, assumes it is a class name,
     * and attempts to instantiate it. If the class does not exist, or does
     * not extend InputFilterInterface, an exception is raised.
     *
     * Otherwise, $spec is passed on to the attached InputFilter Factory
     * instance in order to create the input filter.
     *
     * @throws Exception\DomainException For unknown InputFilter class or invalid InputFilter instance.
     * @throws \Laminas\InputFilter\Exception\RuntimeException
     * @throws \ReflectionException
     */
    protected function prepareAndInjectInputFilter(
        InputFilterInterface|Traversable|array|string $spec,
        FormInterface $form,
        string $method,
    ): void {
        if ($spec instanceof InputFilterInterface) {
            $form->setInputFilter(inputFilter: $spec);
            return;
        }

        if (is_string(value: $spec)) {
            if (!class_exists(class: $spec)) {
                throw new Exception\DomainException(message: sprintf(
                    '%s expects string input filter names to be valid class names; received "%s"',
                    $method,
                    $spec,
                ));
            }
            if (!is_a($spec, InputFilterInterface::class, true)) {
                throw new Exception\DomainException(message: sprintf(
                    '%s expects a valid implementation of Laminas\InputFilter\InputFilterInterface; received "%s"',
                    $method,
                    $spec,
                ));
            }
            $reflection = new ReflectionClass($spec);
            $constructor = $reflection->getConstructor();
            // Laminas InputFilter 3 requires its factory; custom no-argument constructors remain supported.
            $arguments =
                $constructor !== null && $constructor->getNumberOfRequiredParameters() > 0
                    ? [$this->getInputFilterFactory()]
                    : [];
            $filter = Assert::notNull($reflection->newInstanceArgs($arguments));
            $form->setInputFilter(inputFilter: $filter);
            return;
        }

        $factory = $this->getInputFilterFactory();
        $filter = $factory->createInputFilter(inputFilterSpecification: \Jield\Form\InputFilterFactory::normalizeSpecification(ArrayUtils::iteratorToArray(
            $spec,
        )));
        if (method_exists(object_or_class: $filter, method: 'setFactory')) {
            $filter->setFactory($factory);
        }
        $form->setInputFilter(inputFilter: $filter);
    }
}
