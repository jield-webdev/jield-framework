<?php

declare(strict_types=1);

namespace Jield\Form;

use Jield\Form\Element\CollectionInterface;
use Laminas\Hydrator\HydratorInterface;
use Laminas\InputFilter\CollectionInputFilter;
use Laminas\InputFilter\InputFilter;
use Jield\Form\InputFilterAwareInterface;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\InputFilter\InputFilterProviderInterface;
use Laminas\InputFilter\InputProviderInterface;
use Laminas\InputFilter\ReplaceableInputInterface;
use Laminas\Stdlib\ArrayUtils;
use Traversable;

use function array_key_exists;
use function array_keys;
use function assert;
use function in_array;
use function is_array;
use function is_object;
use function iterator_to_array;
use function sprintf;

/**
 * @template TFilteredValues of array<array-key, mixed>
 * @implements FormInterface<TFilteredValues>
 */
class Form extends Fieldset implements FormInterface
{
    /** @var array<string, scalar|null>  */
    protected array $attributes = [
        'method' => 'POST',
    ];

    /**
     * How to bind values to the attached object
     *
     * @var int
     */
    protected int $bindAs = FormInterface::VALUES_NORMALIZED;

    /**
     * Whether or not to bind values to the bound object on successful validation
     *
     * @var int
     */
    protected int $bindOnValidate = FormInterface::BIND_ON_VALIDATE;

    /**
     * Base fieldset to use for hydrating (if none specified, directly hydrate elements)
     *
     * @var null|FieldsetInterface
     */
    protected ?FieldsetInterface $baseFieldset = null;

    /**
     * Data being validated
     *
     * @var null|array
     */
    protected ?array $data = null;

    /** @var null|InputFilterInterface */
    protected ?InputFilterInterface $filter = null;

    /**
     * Whether or not to automatically scan for input filter defaults on
     * attached fieldsets and elements
     *
     * @var bool
     */
    protected bool $useInputFilterDefaults = true;

    /**
     * Has the input filter defaults been added already ?
     *
     * @var bool
     */
    protected bool $hasAddedInputFilterDefaults = false;

    /**
     * Whether or not validation has occurred
     *
     * @var bool
     */
    protected bool $hasValidated = false;

    /**
     * Result of last validation operation
     *
     * @var bool
     */
    protected bool $isValid = false;

    /** @var \Laminas\InputFilter\InputFilterValidationResult<array<array-key, mixed>>|null */
    protected ?\Laminas\InputFilter\InputFilterValidationResult $validationResult = null;

    /**
     * Is the form prepared ?
     *
     * @var bool
     */
    protected bool $isPrepared = false;

    /**
     * Prefer form input filter over input filter defaults
     *
     * @var bool
     */
    protected bool $preferFormInputFilter = true;

    /**
     * Has preferFormInputFilter been set with setPreferFormInputFilter?
     *
     * @var bool
     */
    protected bool $hasSetPreferFormInputFilter = false;

    /**
     * Are the form elements/fieldsets wrapped by the form name ?
     *
     * @var bool
     */
    protected bool $wrapElements = false;

    /**
     * Validation group, if any
     *
     * @var null|array
     */
    protected ?array $validationGroup = null;

    /**
     * Set options for a form. Accepted options are:
     * - prefer_form_input_filter: is form input filter is preferred?
     *
     * @return static
     * @throws Exception\InvalidArgumentException
     */
    public function setOptions(iterable $options): static
    {
        parent::setOptions(options: $options);

        if (isset($this->options['prefer_form_input_filter'])) {
            $this->setPreferFormInputFilter(preferFormInputFilter: \Jield\Stdlib\Value::bool($this->options['prefer_form_input_filter']));
        }

        if (isset($this->options['use_input_filter_defaults'])) {
            $this->setUseInputFilterDefaults(useInputFilterDefaults: \Jield\Stdlib\Value::bool($this->options['use_input_filter_defaults']));
        }

        return $this;
    }

    /** @inheritDoc */
    public function add(Traversable|ElementInterface|array $elementOrFieldset, array $flags = []): static
    {
        // TODO: find a better solution than duplicating the factory code, the problem being that if
        // $elementOrFieldset is an array, it is passed by value, and we don't get back the concrete ElementInterface
        if (
            is_array(value: $elementOrFieldset)
            || $elementOrFieldset instanceof Traversable && !$elementOrFieldset instanceof ElementInterface
        ) {
            $factory = $this->getFormFactory();
            $elementOrFieldset = $factory->create(spec: $elementOrFieldset);
        }

        parent::add(elementOrFieldset: $elementOrFieldset, flags: $flags);

        if ($elementOrFieldset instanceof Fieldset && $elementOrFieldset->useAsBaseFieldset()) {
            $this->baseFieldset = $elementOrFieldset;
        }

        return $this;
    }

    /**
     * Ensures state is ready for use
     *
     * Marshalls the input filter, to ensure validation error messages are
     * available, and prepares any elements and/or fieldsets that require
     * preparation.
     *
     * @return static
     */
    public function prepare(): static
    {
        if ($this->isPrepared) {
            return $this;
        }

        $this->getInputFilter();

        // If the user wants to, elements names can be wrapped by the form's name
        if ($this->wrapElements()) {
            $this->prepareElement(form: $this);
        } else {
            foreach ($this->getIterator() as $elementOrFieldset) {
                if ($elementOrFieldset instanceof Form) {
                    $elementOrFieldset->prepare();
                } elseif ($elementOrFieldset instanceof ElementPrepareAwareInterface) {
                    $elementOrFieldset->prepareElement(form: $this);
                }
            }
        }

        $this->isPrepared = true;
        return $this;
    }

    /**
     * Ensures state is ready for use. Here, we append the name of the fieldsets to every elements in order to avoid
     * name clashes if the same fieldset is used multiple times
     */
    public function prepareElement(FormInterface $form): void
    {
        $name = $this->getName();

        foreach ($this->iterator as $elementOrFieldset) {
            assert(assertion: $elementOrFieldset instanceof ElementInterface);
            if ($form instanceof Form && $form->wrapElements()) {
                $elementOrFieldset->setName(name: (string) ($name) . '[' . (string) $elementOrFieldset->getName() . ']');
            }

            // Recursively prepare elements
            if ($elementOrFieldset instanceof ElementPrepareAwareInterface) {
                $elementOrFieldset->prepareElement(form: $form);
            }
        }
    }

    /**
     * Set data to validate and/or populate elements
     *
     * Typically, also passes data on to the composed input filter.
     *
     * @return static
     * @throws Exception\InvalidArgumentException
     */
    public function setData(iterable $data): static
    {
        if ($data instanceof Traversable) {
            $data = ArrayUtils::iteratorToArray(iterator: $data);
        }

        $this->hasValidated = false;
        $this->data = $data;
        $this->populateValues(data: $data);

        return $this;
    }

    /**
     * Bind an object to the form
     *
     * Ensures the object is populated with validated values.
     *
     * @return static
     * @throws Exception\InvalidArgumentException
     */
    public function bind(object $object, int $flags = FormInterface::VALUES_NORMALIZED): static
    {
        if (!in_array(
            needle: $flags,
            haystack: [FormInterface::VALUES_NORMALIZED, FormInterface::VALUES_RAW],
            strict: true,
        )) {
            throw new Exception\InvalidArgumentException(message: sprintf(
                '%s expects the $flags argument to be one of "%s" or "%s"; received "%s"',
                __METHOD__,
                'Jield\Form\FormInterface::VALUES_NORMALIZED',
                'Jield\Form\FormInterface::VALUES_RAW',
                $flags,
            ));
        }

        if ($this->baseFieldset !== null) {
            $this->baseFieldset->setObject(object: $object);
        }

        $this->bindAs = $flags;
        $this->setObject(object: $object);

        $data = $this->extract();

        $this->populateValues(data: $data, onlyBase: true);

        return $this;
    }

    /**
     * Set the hydrator to use when binding an object to the element
     *
     * @return static
     */
    public function setHydrator(HydratorInterface $hydrator): static
    {
        if ($this->baseFieldset !== null) {
            $this->baseFieldset->setHydrator(hydrator: $hydrator);
        }

        return parent::setHydrator(hydrator: $hydrator);
    }

    /**
     * Bind values to the bound object
     */
    public function bindValues(array $values = [], ?array $validationGroup = null): mixed
    {
        if (!is_object(value: $this->object)) {
            if ($this->baseFieldset === null || $this->baseFieldset->allowValueBinding() === false) {
                return null;
            }
        }
        if (!$this->hasValidated() && !empty($values)) {
            $this->setData(data: $values);
            if (!$this->isValid()) {
                return null;
            }
        } elseif (!$this->isValid) {
            return null;
        }

        $validationResult = $this->validationResult
            ?? throw new Exception\DomainException('No validation result is available for binding.');
        $data = match ($this->bindAs) {
            FormInterface::VALUES_RAW => $validationResult->rawValue(),
            default => $validationResult->value(),
        };

        $data = $this->prepareBindData(values: \Jield\Stdlib\Value::array($data), match: \Jield\Stdlib\Value::array($this->data));
        $validationGroup = $this->getValidationGroup();

        // If there is a base fieldset, only hydrate beginning from the base fieldset
        if ($this->baseFieldset !== null) {
            /** @var mixed $data */
            $data = $data[$this->baseFieldset->getName()] ?? [];
            $this->object = $this->baseFieldset->bindValues(
                \Jield\Stdlib\Value::array($data),
                \Jield\Stdlib\Value::nullableArray($validationGroup[$this->baseFieldset->getName()] ?? []),
            );
        } else {
            $this->object = parent::bindValues(values: $data, validationGroup: $validationGroup);
        }
        return null;
    }

    /**
     * Parse filtered values and return only posted fields for binding
     */
    protected function prepareBindData(array $values, array $match): array
    {
        $data = [];
        /** @var mixed $value */
        foreach ($values as $name => $value) {
            if (!array_key_exists(key: $name, array: $match)) {
                continue;
            }

            if (is_array(value: $value) && is_array(value: $match[$name])) {
                $data[$name] = $this->prepareBindData(values: $value, match: $match[$name]);
            } else {
                $data[$name] = $value;
            }
        }
        return $data;
    }

    /**
     * Set flag indicating whether or not to bind values on successful validation
     *
     * @return static
     * @throws Exception\InvalidArgumentException
     */
    public function setBindOnValidate(int $bindOnValidateFlag): static
    {
        if (!in_array(
            needle: $bindOnValidateFlag,
            haystack: [self::BIND_ON_VALIDATE, self::BIND_MANUAL],
            strict: true,
        )) {
            throw new Exception\InvalidArgumentException(message: sprintf(
                '%s expects the flag to be one of %s::%s or %s::%s',
                __METHOD__,
                static::class,
                'BIND_ON_VALIDATE',
                static::class,
                'BIND_MANUAL',
            ));
        }
        $this->bindOnValidate = $bindOnValidateFlag;
        return $this;
    }

    /**
     * Will we bind values to the bound object on successful validation?
     */
    public function bindOnValidate(): bool
    {
        return static::BIND_ON_VALIDATE === $this->bindOnValidate;
    }

    /**
     * Set the base fieldset to use when hydrating
     *
     * @return static
     * @throws Exception\InvalidArgumentException
     */
    public function setBaseFieldset(FieldsetInterface $baseFieldset): static
    {
        $this->baseFieldset = $baseFieldset;
        return $this;
    }

    /**
     * Get the base fieldset to use when hydrating
     */
    public function getBaseFieldset(): ?FieldsetInterface
    {
        return $this->baseFieldset;
    }

    /**
     * Check if the form has been validated
     */
    public function hasValidated(): bool
    {
        return $this->hasValidated;
    }

    /**
     * Validate the form
     *
     * Typically, will proxy to the composed input filter.
     *
     * @throws Exception\DomainException
     */
    public function isValid(): bool
    {
        if ($this->hasValidated) {
            return $this->isValid;
        }

        $this->isValid = false;

        if (!is_array(value: $this->data) && !is_object(value: $this->object)) {
            throw new Exception\DomainException(message: sprintf(
                '%s is unable to validate as there is no data currently set',
                __METHOD__,
            ));
        }

        if (!is_array(value: $this->data)) {
            $data = $this->extract();
            $this->populateValues(data: $data, onlyBase: true);
            $this->data = $data;
        }

        $filter = $this->getInputFilter();

        $validationGroup = $this->getValidationGroup();
        if ($validationGroup !== null) {
            $this->prepareValidationGroup(formOrFieldset: $this, data: \Jield\Stdlib\Value::array($this->data), validationGroup: $validationGroup);
        }

        $this->validationResult = \Jield\Stdlib\InputFilterValidator::validate(
            $filter, \Jield\Stdlib\Value::array($this->data), $validationGroup,
        );
        $this->isValid = $result = $this->validationResult->valid();
        $this->hasValidated = true;

        if ($result && $this->bindOnValidate()) {
            $this->bindValues();
        }

        if (!$result) {
            $this->setMessages(messages: $this->validationResult->getMessages()->toArray());
        }

        return $result;
    }

    /**
     * Retrieve the validated data
     *
     * By default, retrieves normalized values; pass one of the
     * FormInterface::VALUES_* constants to shape the behavior.
     *
     * @inheritDoc
     * @throws Exception\DomainException
     */
    public function getData(int $flag = FormInterface::VALUES_NORMALIZED): array|object
    {
        if (!$this->hasValidated) {
            throw new Exception\DomainException(message: sprintf(
                '%s cannot return data as validation has not yet occurred',
                __METHOD__,
            ));
        }

        if ($flag !== FormInterface::VALUES_AS_ARRAY && is_object(value: $this->object)) {
            return $this->object;
        }

        $validationResult = $this->validationResult
            ?? throw new Exception\DomainException('No validation result is available.');
        return \Jield\Stdlib\Value::arrayOrObject($flag === FormInterface::VALUES_RAW ? $validationResult->rawValue() : $validationResult->value());
    }

    /**
     * Set the validation group (set of values to validate)
     *
     * Typically, proxies to the composed input filter
     *
     * @return static
     */
    public function setValidationGroup(array $group): static
    {
        $this->hasValidated = false;
        $this->validationGroup = $group;
        return $this;
    }

    public function setValidateAll(): void
    {
        $this->hasValidated = false;
        $this->validationGroup = null;
    }

    /**
     * Retrieve the current validation group, if any
     *
     * @return null|array
     */
    public function getValidationGroup(): ?array
    {
        return $this->validationGroup;
    }

    /**
     * Prepare the validation group in case Collection elements were used (this function also handle
     * the case where elements could have been dynamically added or removed from a collection using JavaScript)
     */
    protected function prepareValidationGroup(Fieldset $formOrFieldset, array $data, array &$validationGroup): void
    {
        $this->prepareFieldsetValidationGroup(
            formOrFieldset: $formOrFieldset,
            data: $data,
            validationGroup: $validationGroup,
        );
    }

    /**
     * @fixme This is here to preserve BC on `prepareValidationGroup` and can be removed on next major if that method's
     *        signature is changed to accept a `FieldsetInterface`
     */
    private function prepareFieldsetValidationGroup(
        FieldsetInterface $formOrFieldset,
        array $data,
        array &$validationGroup,
    ): void {
        $elements = iterator_to_array(iterator: $formOrFieldset->getIterator());
        /** @var mixed $value */
        foreach ($validationGroup as $key => &$value) {
            if (!isset($elements[$key])) {
                continue;
            }
            $fieldset = $elements[$key];

            if ($fieldset instanceof CollectionInterface) {
                if (!isset($data[$key]) && $fieldset->getCount() === 0) {
                    unset($validationGroup[$key]);
                    continue;
                }

                $values = [];

                if (isset($data[$key])) {
                    foreach (array_keys(array: \Jield\Stdlib\Value::array($data[$key])) as $cKey) {
                        $values[$cKey] = $value;
                    }
                }

                $value = $values;
            }

            if (!isset($data[$key])) {
                $data[$key] = [];
            }
            $childGroup = \Jield\Stdlib\Value::array($validationGroup[$key]);
            $this->prepareFieldsetValidationGroup(
                formOrFieldset: \Jield\Stdlib\Value::instance($fieldset, \Jield\Form\FieldsetInterface::class),
                data: \Jield\Stdlib\Value::array($data[$key]),
                validationGroup: $childGroup,
            );
            $validationGroup[$key] = $childGroup;
        }
    }

    /**
     * Set the input filter used by this form
     *
     * @param InputFilterInterface<TFilteredValues> $inputFilter
     * @return static
     */
    public function setInputFilter(InputFilterInterface $inputFilter): static
    {
        $this->hasValidated = false;
        $this->hasAddedInputFilterDefaults = false;
        $this->filter = $inputFilter;

        if (false === $this->hasSetPreferFormInputFilter) {
            $this->preferFormInputFilter = false;
        }

        return $this;
    }

    /**
     * Retrieve input filter used by this form
     *
     * @return InputFilterInterface<TFilteredValues>
     */
    public function getInputFilter(): InputFilterInterface
    {
        if ($this->object instanceof InputFilterAwareInterface) {
            if (null === $this->baseFieldset) {
                $this->filter = $this->object->getInputFilter();
            } else {
                $name = (string) $this->baseFieldset->getName();
                if (!$this->filter instanceof InputFilterInterface || !$this->filter->has($name)) {
                    $filter = new InputFilter($this->getFormFactory()->getInputFilterFactory());
                    $filter->add(input: $this->object->getInputFilter(), name: $name);
                    $this->filter = $filter;
                }
            }
        }

        if (!isset($this->filter)) {
            $this->filter = new InputFilter($this->getFormFactory()->getInputFilterFactory());
        }

        if (!$this->hasAddedInputFilterDefaults && $this->useInputFilterDefaults()) {
            $this->attachInputFilterDefaults(inputFilter: $this->filter, fieldset: $this);
            $this->hasAddedInputFilterDefaults = true;
        }

        return $this->filter;
    }

    /**
     * Set flag indicating whether or not to scan elements and fieldsets for defaults
     *
     * @return static
     */
    public function setUseInputFilterDefaults(bool $useInputFilterDefaults): static
    {
        $this->useInputFilterDefaults = $useInputFilterDefaults;
        return $this;
    }

    /**
     * Should we use input filter defaults from elements and fieldsets?
     */
    public function useInputFilterDefaults(): bool
    {
        return $this->useInputFilterDefaults;
    }

    /**
     * Set flag indicating whether or not to prefer the form input filter over element and fieldset defaults
     *
     * @return static
     */
    public function setPreferFormInputFilter(bool $preferFormInputFilter): static
    {
        $this->preferFormInputFilter = $preferFormInputFilter;
        $this->hasSetPreferFormInputFilter = true;
        return $this;
    }

    /**
     * Should we use form input filter over element input filter defaults from elements and fieldsets?
     */
    public function getPreferFormInputFilter(): bool
    {
        return $this->preferFormInputFilter;
    }

    /**
     * Attach defaults provided by the elements to the input filter
     *
     * @param  FieldsetInterface $fieldset Fieldset to traverse when looking for default inputs
     */
    public function attachInputFilterDefaults(InputFilterInterface $inputFilter, FieldsetInterface $fieldset): void
    {
        $formFactory = $this->getFormFactory();
        $inputFactory = $formFactory->getInputFilterFactory();

        if (
            $fieldset instanceof CollectionInterface
            && ($targetElement = $fieldset->getTargetElement()) instanceof FieldsetInterface
        ) {
            $elements = $targetElement->getElements();
        } else {
            $elements = $fieldset->getElements();
        }

        if (
            !$fieldset instanceof CollectionInterface
            || !$fieldset->getTargetElement() instanceof FieldsetInterface
            || $inputFilter instanceof CollectionInputFilter
        ) {
            foreach ($elements as $name => $element) {
                $name = (string) $name;
                if ($this->preferFormInputFilter && $inputFilter->has($name)) {
                    continue;
                }

                if (!$element instanceof InputProviderInterface) {
                    if ($inputFilter->has($name)) {
                        continue;
                    }
                    // Create a new empty default input for this element
                    $spec = ['name' => $name, 'required' => false];
                    $input = $inputFactory->createInput(inputSpecification: $spec);
                } else {
                    // Create an input based on the specification returned from the element
                    $spec = $element->getInputSpecification();
                    $input = $inputFactory->createInput(inputSpecification: $spec);

                    if ($input instanceof \Laminas\InputFilter\MutableInputInterface && $inputFilter->has($name) && $inputFilter instanceof ReplaceableInputInterface) {
                        $input->merge(input: \Jield\Stdlib\Value::instance($inputFilter->get($name), \Laminas\InputFilter\InputInterface::class));
                        $inputFilter->replace(input: $input, name: $name);
                        continue;
                    }

                    // If we are dealing with a collection input filter, check
                    // the input filter it composes for an element of the same
                    // name as was done above.
                    if (
                        $input instanceof \Laminas\InputFilter\MutableInputInterface
                        && $inputFilter instanceof CollectionInputFilter
                        && $inputFilter->getInputFilter()->has($name)
                        && $inputFilter->getInputFilter() instanceof ReplaceableInputInterface
                    ) {
                        $collectionInputFilter = $inputFilter->getInputFilter();
                        assert($collectionInputFilter instanceof ReplaceableInputInterface);
                        $input->merge(input: \Jield\Stdlib\Value::instance($collectionInputFilter->get($name), \Laminas\InputFilter\InputInterface::class));
                        $collectionInputFilter->replace(input: $input, name: $name);
                        continue;
                    }
                }

                // Add element input filter to CollectionInputFilter
                if ($inputFilter instanceof CollectionInputFilter && !$inputFilter->getInputFilter()->has($name)) {
                    $inputFilter->getInputFilter()->add(input: $input, name: $name);
                } else {
                    $inputFilter->add(input: $input, name: $name);
                }
            }

            if ($fieldset === $this && $fieldset instanceof InputFilterProviderInterface) {
                /** @var mixed $name
                 * @var mixed $spec
                 */
                foreach ($fieldset->getInputFilterSpecification() as $name => $spec) {
                    $spec['name'] ??= $name;
                    $input = $inputFactory->createInput(inputSpecification: $spec);
                    $inputFilter->add(input: $input, name: \Jield\Stdlib\Value::nullableKey($name));
                }
            }
        }

        foreach ($fieldset->getFieldsets() as $name => $childFieldset) {
            if (!$childFieldset instanceof InputFilterProviderInterface) {
                if (!$inputFilter->has($name)) {
                    // Add a new empty input filter if it does not exist (or the fieldset's object input filter),
                    // so that elements of nested fieldsets can be recursively added
                    if ($childFieldset->getObject() instanceof InputFilterAwareInterface) {
                        $inputFilter->add(input: \Jield\Stdlib\Value::call($childFieldset->getObject(), 'getInputFilter'), name: $name);
                    } else if (
                        $childFieldset instanceof CollectionInterface
                        && null !== ($targetElement = $childFieldset->getTargetElement())
                        && $targetElement instanceof InputFilterProviderInterface
                    ) {
                        // Add input filter for collections via getInputFilterSpecification()
                        $collectionContainerFilter = new CollectionInputFilter(\Jield\Form\InputFilterFactory::create());

                        $spec = $targetElement->getInputFilterSpecification();
                        $filter = $inputFactory->createInputFilter(inputFilterSpecification: \Jield\Form\InputFilterFactory::normalizeSpecification(
                            $spec,
                        ));

                        $collectionContainerFilter->setInputFilter(inputFilter: $filter);

                        $inputFilter->add(input: $collectionContainerFilter, name: $name);

                        // We need to copy the inputs to the collection input filter
                        if ($inputFilter instanceof CollectionInputFilter) {
                            $inputFilter = $this->addInputsToCollectionInputFilter(inputFilter: $inputFilter);
                        }

                        // Add child elements from target element
                        $childFieldset = $targetElement;
                    } else {
                        $inputFilter->add(
                            input: new InputFilter($this->getFormFactory()->getInputFilterFactory()),
                            name: $name,
                        );
                    }
                }

                $fieldsetFilter = $inputFilter->get($name);

                if (!$fieldsetFilter instanceof InputFilterInterface) {
                    // Input attached for fieldset, not input filter; nothing more to do.
                    continue;
                }

                // Traverse the elements of the fieldset, and attach any
                // defaults to the fieldset's input filter
                $this->attachInputFilterDefaults(inputFilter: $fieldsetFilter, fieldset: \Jield\Stdlib\Value::instance($childFieldset, \Jield\Form\FieldsetInterface::class));
                continue;
            }

            if ($inputFilter->has($name)) {
                // if we already have an input/filter by this name, use it
                continue;
            }

            // Create an input filter based on the specification returned from the fieldset
            $spec = $childFieldset->getInputFilterSpecification();
            $filter = $inputFactory->createInputFilter(inputFilterSpecification: \Jield\Form\InputFilterFactory::normalizeSpecification(
                $spec,
            ));
            $inputFilter->add(input: $filter, name: $name);

            // Recursively attach sub filters
            $this->attachInputFilterDefaults(inputFilter: $filter, fieldset: $childFieldset);

            // We need to copy the inputs to the collection input filter to ensure that all sub filters are added
            if ($inputFilter instanceof CollectionInputFilter) {
                $inputFilter = $this->addInputsToCollectionInputFilter(inputFilter: $inputFilter);
            }
        }
    }

    /**
     * Add inputs to CollectionInputFilter
     */
    private function addInputsToCollectionInputFilter(CollectionInputFilter $inputFilter): CollectionInputFilter
    {
        foreach ($inputFilter->getInputs() as $name => $input) {
            if ($inputFilter->getInputFilter()->has($name)) {
                continue;
            }

            $inputFilter->getInputFilter()->add(input: $input, name: $name);
        }

        return $inputFilter;
    }

    /**
     * Are the form elements/fieldsets names wrapped by the form name ?
     *
     * @return static
     */
    public function setWrapElements(bool $wrapElements): static
    {
        $this->wrapElements = $wrapElements;
        return $this;
    }

    /**
     * If true, form elements/fieldsets name's are wrapped around the form name itself
     */
    public function wrapElements(): bool
    {
        return $this->wrapElements;
    }

    /**
     * @inheritDoc
     * @param bool $onlyBase
     */
    public function populateValues(iterable $data, bool $onlyBase = false): void
    {
        if ($data instanceof Traversable) {
            $data = ArrayUtils::iteratorToArray(iterator: $data);
        }

        if ($onlyBase && $this->baseFieldset !== null) {
            $name = (string) $this->baseFieldset->getName();
            if (array_key_exists(key: $name, array: \Jield\Stdlib\Value::array($data))) {
                $this->baseFieldset->populateValues(data: \Jield\Stdlib\Value::iterable($data[$name]));
            }
        } else {
            parent::populateValues(data: $data);
        }
    }

    /**
     * Recursively extract values for elements and sub-fieldsets
     */
    protected function extract(): array
    {
        if ($this->baseFieldset !== null) {
            assert(assertion: $this->baseFieldset instanceof Fieldset);
            $name = (string) $this->baseFieldset->getName();
            $values = [$name => $this->baseFieldset->extract()];
        } else {
            $values = parent::extract();
        }

        return $values;
    }

    /**
     * Set the input filter by name to use when binding an object to the element.
     *
     * The input filter manager {@see InputFilterPluginManager} is used via the
     * form factory {@see Factory} and the input filter factory
     * {@see \Laminas\InputFilter\Factory} to fetch the input filter.
     */
    public function setInputFilterByName(string $inputFilterName): void
    {
        $inputFilter = $this->getFormFactory()->getInputFilterFactory()->createInputFilter(['type' => $inputFilterName]);
        $this->setInputFilter(inputFilter: \Jield\Stdlib\Value::instance($inputFilter, \Laminas\InputFilter\InputFilterInterface::class));
    }
}
