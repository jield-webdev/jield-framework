<?php

declare(strict_types=1);

namespace Jield\Form;

use ArrayAccess;
use Laminas\Hydrator;
use Laminas\InputFilter\Factory as InputFilterFactory;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\ArrayUtils;
use Traversable;

use function assert;
use function class_exists;
use function get_debug_type;
use function is_array;
use function is_iterable;
use function is_string;
use function method_exists;
use function sprintf;

/** @final */
class Factory
{
    /** @var null|InputFilterFactory */
    protected ?InputFilterFactory $inputFilterFactory = null;

    /** @var null|FormElementManager */
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
     *
     * @return static
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
            $this->setInputFilterFactory(inputFilterFactory: \Jield\Form\InputFilterFactory::create());
            assert(assertion: null !== $this->inputFilterFactory);
        }
        return $this->inputFilterFactory;
    }

    /**
     * Set the form element manager
     *
     * @return static
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
            $this->setFormElementManager(
                formElementManager: new FormElementManager(creationContext: new ServiceManager()),
            );
            assert(assertion: null !== $this->formElementManager);
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
     * @param Traversable|ArrayAccess|array $spec
     * @throws Exception\DomainException
     */
    public function create(Traversable|ArrayAccess|array $spec): ElementInterface
    {
        $spec = $this->validateSpecification(spec: $spec, method: __METHOD__);
        /** @var mixed $type */
        $type = $spec['type'] ?? Element::class;

        $element = $this->getFormElementManager()->get(\Jield\Stdlib\Value::classString($type));

        if ($element instanceof FormInterface) {
            return $this->configureForm(form: $element, spec: $spec);
        }

        if ($element instanceof FieldsetInterface) {
            return $this->configureFieldset(fieldset: $element, spec: $spec);
        }

        return $this->configureElement(element: $element, spec: $spec);

        throw new Exception\DomainException(message: sprintf(
            '%s expects the $spec["type"] to implement one of %s, %s, or %s; received %s',
            __METHOD__,
            ElementInterface::class,
            FieldsetInterface::class,
            FormInterface::class,
            \Jield\Stdlib\Value::scalar($type),
        ));
    }

    /**
     * Create an element
     */
    public function createElement(array $spec): ElementInterface
    {
        if (!isset($spec['type'])) {
            $spec['type'] = Element::class;
        }

        return $this->create(spec: $spec);
    }

    /**
     * Create a fieldset
     */
    public function createFieldset(array $spec): FieldsetInterface
    {
        if (!isset($spec['type'])) {
            $spec['type'] = Fieldset::class;
        }

        return \Jield\Stdlib\Value::instance($this->create(spec: $spec), \Jield\Form\FieldsetInterface::class);
    }

    /**
     * Create a form
     */
    public function createForm(array $spec): FormInterface
    {
        if (!isset($spec['type'])) {
            $spec['type'] = Form::class;
        }

        return \Jield\Stdlib\Value::instance($this->create(spec: $spec), \Jield\Form\FormInterface::class);
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
     * @param Traversable|ArrayAccess|array $spec
     * @throws Exception\DomainException
     */
    public function configureElement(ElementInterface $element, Traversable|ArrayAccess|array $spec): ElementInterface
    {
        $spec = $this->validateSpecification(spec: $spec, method: __METHOD__);

        /** @var mixed $name */
        $name = $spec['name'] ?? null;
        /** @var mixed $options */
        $options = $spec['options'] ?? null;
        /** @var mixed $attributes */
        $attributes = $spec['attributes'] ?? null;

        if ($name !== null && $name !== '') {
            $element->setName(name: \Jield\Stdlib\Value::string($name));
        }

        if (is_iterable(value: $options)) {
            $element->setOptions(options: $options);
        }

        if (is_iterable(value: $attributes)) {
            $element->setAttributes(arrayOrTraversable: $attributes);
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
     * @param Traversable|ArrayAccess|array $spec
     * @throws Exception\DomainException
     */
    public function configureFieldset(
        FieldsetInterface $fieldset,
        Traversable|ArrayAccess|array $spec,
    ): FieldsetInterface {
        $spec = $this->validateSpecification(spec: $spec, method: __METHOD__);
        $fieldset = $this->configureElement(element: $fieldset, spec: $spec);
        assert(assertion: $fieldset instanceof FieldsetInterface);

        if (isset($spec['object'])) {
            $this->prepareAndInjectObject(objectName: \Jield\Stdlib\Value::string($spec['object']), fieldset: $fieldset, method: __METHOD__);
        }

        if (isset($spec['hydrator'])) {
            $this->prepareAndInjectHydrator(hydratorOrName: \Jield\Stdlib\Value::instanceOrArrayOrString($spec['hydrator'], \Laminas\Hydrator\HydratorInterface::class), fieldset: $fieldset, method: __METHOD__);
        }

        if (isset($spec['elements'])) {
            $this->prepareAndInjectElements(elements: $spec['elements'], fieldset: $fieldset, method: __METHOD__);
        }

        if (isset($spec['fieldsets'])) {
            $this->prepareAndInjectFieldsets(
                fieldsets: $spec['fieldsets'],
                masterFieldset: $fieldset,
                method: __METHOD__,
            );
        }

        /** @var mixed $factory */
        $factory = $spec['factory'] ?? $this;
        $this->prepareAndInjectFactory(factoryOrName: \Jield\Stdlib\Value::instanceOrArrayOrString($factory, \Jield\Form\Factory::class), fieldset: $fieldset, method: __METHOD__);

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
     *
     * @param Traversable|ArrayAccess|array $spec
     */
    public function configureForm(FormInterface $form, Traversable|ArrayAccess|array $spec): FormInterface
    {
        $spec = $this->validateSpecification(spec: $spec, method: __METHOD__);
        $form = $this->configureFieldset(fieldset: $form, spec: $spec);
        assert(assertion: $form instanceof FormInterface);

        if (isset($spec['input_filter'])) {
            $this->prepareAndInjectInputFilter(spec: $spec['input_filter'], form: $form, method: __METHOD__);
        }

        if (isset($spec['validation_group'])) {
            $form->setValidationGroup(group: \Jield\Stdlib\Value::array($spec['validation_group']));
        }

        return $form;
    }

    /**
     * Validate a provided specification
     *
     * Ensures we have an array, Traversable, or ArrayAccess object, and returns it.
     *
     * @param Traversable|ArrayAccess|array $spec
     * @param  string $method Method invoking the validator
     * @return array|ArrayAccess
     * @throws Exception\InvalidArgumentException For invalid $spec.
     */
    protected function validateSpecification(Traversable|ArrayAccess|array $spec, string $method): ArrayAccess|array
    {
        if (is_array(value: $spec)) {
            return $spec;
        }

        if ($spec instanceof Traversable) {
            return \Jield\Stdlib\Value::array(ArrayUtils::iteratorToArray(iterator: $spec));
        }

        

        return $spec;
    }

    /**
     * Takes a list of element specifications, creates the elements, and injects them into the provided fieldset
     *
     * @param Traversable|ArrayAccess|array $elements
     * @param  string $method Method invoking this one (for exception messages)
     */
    protected function prepareAndInjectElements(
        Traversable|ArrayAccess|array $elements,
        FieldsetInterface $fieldset,
        string $method,
    ): void {
        $elements = $this->validateSpecification(spec: $elements, method: $method);

        /** @var mixed $elementSpecification */
        foreach ($elements as $elementSpecification) {
            if (null === $elementSpecification) {
                continue;
            }

            /** @var mixed $flags */
            $flags = $elementSpecification['flags'] ?? [];
            /** @var mixed $spec */
            $spec = $elementSpecification['spec'] ?? [];

            if (!isset($spec['type'])) {
                \Jield\Stdlib\Value::setPath($spec, ['type'], Element::class);
            }

            $element = $this->create(spec: $spec);
            $fieldset->add(elementOrFieldset: $element, flags: \Jield\Stdlib\Value::array($flags));
        }
    }

    /**
     * Takes a list of fieldset specifications, creates the fieldsets, and injects them into the master fieldset
     *
     * @param Traversable|ArrayAccess|array $fieldsets
     * @param  string $method Method invoking this one (for exception messages)
     */
    public function prepareAndInjectFieldsets(
        Traversable|ArrayAccess|array $fieldsets,
        FieldsetInterface $masterFieldset,
        string $method,
    ): void {
        $fieldsets = $this->validateSpecification(spec: $fieldsets, method: $method);

        /** @var mixed $fieldsetSpecification */
        foreach ($fieldsets as $fieldsetSpecification) {
            /** @var mixed $flags */
            $flags = $fieldsetSpecification['flags'] ?? [];
            /** @var mixed $spec */
            $spec = $fieldsetSpecification['spec'] ?? [];

            $fieldset = $this->createFieldset(spec: \Jield\Stdlib\Value::array($spec));
            $masterFieldset->add(elementOrFieldset: $fieldset, flags: \Jield\Stdlib\Value::array($flags));
        }
    }

    /**
     * Prepare and inject an object
     *
     * Takes a string indicating a class name, instantiates the class
     * by that name, and injects the class instance as the bound object.
     *
     * @throws Exception\DomainException
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

        $fieldset->setObject(object: \Jield\Stdlib\Value::instantiate($objectName));
    }

    /**
     * Prepare and inject a named hydrator
     *
     * Takes a string indicating a hydrator class name (or a concrete instance), try first to instantiates the class
     * by pulling it from service manager, and injects the hydrator instance into the form.
     *
     * @param  string|array|Hydrator\HydratorInterface $hydratorOrName
     * @throws Exception\DomainException If $hydratorOrName is not a string, does not resolve to a known class, or
     *                                   the class does not implement Hydrator\HydratorInterface.
     */
    protected function prepareAndInjectHydrator(
        Hydrator\HydratorInterface|array|string $hydratorOrName,
        FieldsetInterface $fieldset,
        string $method,
    ): void {
        if ($hydratorOrName instanceof Hydrator\HydratorInterface) {
            $fieldset->setHydrator(hydrator: $hydratorOrName);
            return;
        }

        if (is_array(value: $hydratorOrName)) {
            if (!isset($hydratorOrName['type'])) {
                throw new Exception\DomainException(message: sprintf(
                    '%s expects array specification to have a type value',
                    $method,
                ));
            }
            /** @var mixed $hydratorOptions */
            $hydratorOptions = $hydratorOrName['options'] ?? [];
            /** @var mixed $hydratorOrName */
            $hydratorOrName = $hydratorOrName['type'];
        } else {
            $hydratorOptions = [];
        }

        if (is_string(value: $hydratorOrName)) {
            /** @var mixed $hydrator */
            $hydrator = $this->getFormElementManager()->getHydratorFromName(hydratorName: $hydratorOrName);
        }

        if (!isset($hydrator) || !$hydrator instanceof Hydrator\HydratorInterface) {
            throw new Exception\DomainException(message: sprintf(
                '%s expects a valid implementation of Laminas\Hydrator\HydratorInterface; received "%s"',
                $method,
                \Jield\Stdlib\Value::scalar($hydratorOrName),
            ));
        }

        if (!empty($hydratorOptions) && $hydrator instanceof Hydrator\HydratorOptionsInterface) {
            $hydrator->setOptions(options: \Jield\Stdlib\Value::array($hydratorOptions));
        }

        $fieldset->setHydrator(hydrator: $hydrator);
    }

    /**
     * Prepare and inject a named factory
     *
     * Takes a string indicating a factory class name (or a concrete instance), try first to instantiates the class
     * by pulling it from service manager, and injects the factory instance into the fieldset.
     *
     * @param array|string|Factory $factoryOrName
     * @throws Exception\DomainException If $factoryOrName is not a string, does not resolve to a known class, or
     *                                   the class does not extend Form\Factory.
     */
    protected function prepareAndInjectFactory(
        Factory|array|string $factoryOrName,
        FieldsetInterface $fieldset,
        string $method,
    ): void {
        if (is_array(value: $factoryOrName)) {
            if (!isset($factoryOrName['type'])) {
                throw new Exception\DomainException(message: sprintf(
                    '%s expects array specification to have a type value',
                    $method,
                ));
            }
            /** @var mixed $factoryOrName */
            $factoryOrName = $factoryOrName['type'];
        }

        if (is_string(value: $factoryOrName)) {
            /** @var mixed $factoryOrName */
            $factoryOrName = $this->getFormElementManager()->getFactoryFromName(factoryName: $factoryOrName);
        }

        if (!$factoryOrName instanceof Factory) {
            throw new Exception\DomainException(message: sprintf(
                '%s expects a valid extension of Jield\Form\Factory; received "%s"',
                $method,
                \Jield\Stdlib\Value::scalar($factoryOrName),
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
     * @param Traversable|array|string $spec
     * @throws Exception\DomainException For unknown InputFilter class or invalid InputFilter instance.
     */
    protected function prepareAndInjectInputFilter(
        Traversable|array|string $spec,
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
            $filter = \Jield\Stdlib\Value::instantiate($spec);
            if (!$filter instanceof InputFilterInterface) {
                throw new Exception\DomainException(message: sprintf(
                    '%s expects a valid implementation of Laminas\InputFilter\InputFilterInterface; received "%s"',
                    $method,
                    $spec,
                ));
            }
            $form->setInputFilter(inputFilter: $filter);
            return;
        }

        $factory = $this->getInputFilterFactory();
        $filter = $factory->createInputFilter(inputFilterSpecification: \Jield\Form\InputFilterFactory::normalizeSpecification(
            \Jield\Stdlib\Value::array($spec),
        ));
        if (method_exists(object_or_class: $filter, method: 'setFactory')) {
            $filter->setFactory($factory);
        }
        $form->setInputFilter(inputFilter: $filter);
    }
}
