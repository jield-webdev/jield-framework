<?php

declare(strict_types=1);

namespace Jield\Form;

use Jield\Form\Element\CollectionInterface;
use Jield\Stdlib\Value;
use Laminas\Hydrator;
use Laminas\Hydrator\HydratorAwareInterface;
use Laminas\Hydrator\HydratorInterface;
use Laminas\Stdlib\ArrayUtils;
use Laminas\Stdlib\PriorityList;
use ReflectionClass;
use Traversable;

use function array_key_exists;
use function assert;
use function get_debug_type;
use function in_array;
use function is_array;
use function is_iterable;
use function is_numeric;
use function is_object;
use function is_string;
use function ltrim;
use function sprintf;

class Fieldset extends Element implements FieldsetInterface
{
    /** @var null|Factory */
    protected ?Factory $factory = null;

    /** @var array<array-key, ElementInterface> */
    protected array $elements = [];

    /** @var array<array-key, FieldsetInterface> */
    protected array $fieldsets = [];

    /** @var array */
    protected array $messages = [];

    /** @var PriorityList<string, ElementInterface> */
    protected PriorityList $iterator;

    /**
     * Hydrator to use with bound object
     *
     * @var null|Hydrator\HydratorInterface
     */
    protected ?HydratorInterface $hydrator = null;

    /**
     * The object bound to this fieldset, if any
     *
     * @var null|object|array<array-key, mixed>
     */
    protected object|array|null $object = null;

    /**
     * Should this fieldset be used as a base fieldset in the parent form ?
     *
     * @var bool
     */
    protected bool $useAsBaseFieldset = false;

    /**
     * The class or interface of objects that can be bound to this fieldset.
     *
     * @var null|class-string
     */
    protected ?string $allowedObjectBindingClass = null;

    /**
     * @param int|string|null $name    Optional name for the element
     * @param  array            $options Optional options for the element
     */
    public function __construct(int|string|null $name = null, array $options = [])
    {
        /** @var PriorityList<string, ElementInterface> $list */
        $list = new PriorityList();
        $this->iterator = $list;
        $this->iterator->isLIFO(flag: false);
        parent::__construct(name: $name, options: $options);
    }

    /**
     * Set options for a fieldset. Accepted options are:
     * - use_as_base_fieldset: is this fieldset use as the base fieldset?
     *
     * @inheritDoc
     * @throws Exception\InvalidArgumentException
     */
    public function setOptions(iterable $options): static
    {
        parent::setOptions(options: $options);

        if (isset($this->options['use_as_base_fieldset'])) {
            $this->setUseAsBaseFieldset(useAsBaseFieldset: Value::bool(
                $this->options['use_as_base_fieldset'],
            ));
        }

        if (isset($this->options['allowed_object_binding_class'])) {
            $this->setAllowedObjectBindingClass(
                allowObjectBindingClass: Value::classString($this->options['allowed_object_binding_class']),
            );
        }

        return $this;
    }

    /** @inheritDoc */
    public function setFormFactory(Factory $formFactory): static
    {
        $this->factory = $formFactory;
        return $this;
    }

    /**
     * Retrieve composed form factory
     *
     * Lazy-loads one if none present.
     */
    public function getFormFactory(): Factory
    {
        if (null === $this->factory) {
            $this->setFormFactory(formFactory: new Factory());
            assert(assertion: null !== $this->factory);
        }

        return $this->factory;
    }

    /** @inheritDoc */
    public function add(Traversable|ElementInterface|array $elementOrFieldset, array $flags = []): static
    {
        if (
            is_array(value: $elementOrFieldset)
            || $elementOrFieldset instanceof Traversable && !$elementOrFieldset instanceof ElementInterface
        ) {
            $factory = $this->getFormFactory();
            $elementOrFieldset = $factory->create(spec: $elementOrFieldset);
        }

        $name = $elementOrFieldset->getName();
        if (isset($flags['name']) && is_string(value: $flags['name']) && $flags['name'] !== '') {
            $name = $flags['name'];

            // Rename the element or fieldset to the specified alias
            $elementOrFieldset->setName(name: $name);
        }

        if (null === $name || '' === $name) {
            throw new Exception\InvalidArgumentException(message: sprintf(
                '%s: element or fieldset provided is not named, and no name provided in flags',
                __METHOD__,
            ));
        }
        $order = 0;
        if (isset($flags['priority']) && is_numeric(value: $flags['priority'])) {
            $order = (int) $flags['priority'];
        }

        $this->iterator->insert(name: $name, value: $elementOrFieldset, priority: $order);

        if ($elementOrFieldset instanceof FieldsetInterface) {
            $this->fieldsets[$name] = $elementOrFieldset;
            return $this;
        }

        $this->elements[$name] = $elementOrFieldset;
        return $this;
    }

    /** @inheritDoc */
    public function has(string $elementOrFieldset): bool
    {
        return $this->iterator->get($elementOrFieldset) !== null;
    }

    /**
     * Retrieve a named element or fieldset
     *
     * @return FieldsetInterface|ElementInterface
     */
    public function get(string $elementOrFieldset): ElementInterface
    {
        $element = $this->iterator->get($elementOrFieldset);
        if (!$element) {
            throw new Exception\InvalidElementException(message: sprintf(
                'No element by the name of [%s] found in form',
                $elementOrFieldset,
            ));
        }
        return $element;
    }

    /** @inheritDoc */
    public function remove(string $elementOrFieldset): static
    {
        if (!$this->has(elementOrFieldset: $elementOrFieldset)) {
            return $this;
        }

        $this->iterator->remove(name: $elementOrFieldset);

        if (isset($this->fieldsets[$elementOrFieldset])) {
            unset($this->fieldsets[$elementOrFieldset]);
            return $this;
        }

        unset($this->elements[$elementOrFieldset]);
        return $this;
    }

    /** @inheritDoc */
    public function setPriority(string $elementOrFieldset, int $priority): static
    {
        $this->iterator->setPriority(name: $elementOrFieldset, priority: $priority);
        return $this;
    }

    /** @inheritDoc */
    public function getElements(): array
    {
        return $this->elements;
    }

    /** @inheritDoc */
    public function getFieldsets(): array
    {
        return $this->fieldsets;
    }

    /** @inheritDoc */
    public function setMessages(iterable $messages): static
    {
        /** @var mixed $key
         * @var mixed $messageSet
         */
        foreach ($messages as $key => $messageSet) {
            if (!$this->has(elementOrFieldset: (string) $key)) {
                $this->messages[Value::key($key)] = $messageSet;
                continue;
            }

            $element = $this->get(elementOrFieldset: (string) $key);
            $element->setMessages(messages: Value::iterable($messageSet));
        }

        return $this;
    }

    /**
     * Get validation error messages, if any
     *
     * Returns a hash of element names/messages for all elements failing
     * validation, or, if $elementName is provided, messages for that element
     * only.
     *
     * @inheritDoc
     */
    public function getMessages(?string $elementName = null): array
    {
        if (null === $elementName) {
            $messages = $this->messages;
            foreach ($this->iterator as $name => $element) {
                assert(assertion: $element instanceof ElementInterface);
                $messageSet = $element->getMessages();
                if ($messageSet === []) {
                    continue;
                }
                $messages[$name] = $messageSet;
            }
            return $messages;
        }

        if (!$this->has(elementOrFieldset: $elementName)) {
            throw new Exception\InvalidArgumentException(message: sprintf(
                'Invalid element name "%s" provided to %s',
                $elementName,
                __METHOD__,
            ));
        }

        $element = $this->get(elementOrFieldset: $elementName);
        return $element->getMessages();
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
            $elementOrFieldset->setName(name: (string) $name . '[' . (string) $elementOrFieldset->getName() . ']');

            // Recursively prepare elements
            if ($elementOrFieldset instanceof ElementPrepareAwareInterface) {
                $elementOrFieldset->prepareElement(form: $form);
            }
        }
    }

    /**
     * Recursively populate values of attached elements and fieldsets
     *
     * @throws Exception\InvalidArgumentException
     */
    public function populateValues(iterable $data): void
    {
        if ($data instanceof Traversable) {
            $data = ArrayUtils::iteratorToArray(iterator: $data);
        }

        foreach ($this->iterator as $name => $elementOrFieldset) {
            assert(assertion: $elementOrFieldset instanceof ElementInterface);
            $valueExists = array_key_exists(key: $name, array: Value::array($data));

            if ($elementOrFieldset instanceof FieldsetInterface) {
                if ($valueExists && is_iterable(value: $data[$name])) {
                    $elementOrFieldset->populateValues(data: $data[$name]);
                    continue;
                }

                if ($elementOrFieldset instanceof Element\CollectionInterface) {
                    if ($valueExists && null !== $data[$name]) {
                        $elementOrFieldset->populateValues(data: Value::iterable($data[$name]));
                        continue;
                    }

                    /* This ensures that collections with allow_remove don't re-create child
                     * elements if they all were removed */
                    $elementOrFieldset->populateValues(data: []);
                    continue;
                }
            }

            if ($valueExists) {
                $elementOrFieldset->setValue(value: $data[$name]);
            }
        }
    }

    /** @inheritDoc */
    public function count(): int
    {
        return $this->iterator->count();
    }

    /**
     * IteratorAggregate: return internal iterator
     *
     * @return PriorityList<string, ElementInterface>
     */
    public function getIterator(): PriorityList
    {
        return $this->iterator;
    }

    /**
     * Set the object used by the hydrator
     *
     * @param mixed $object
     * @return static
     * @throws Exception\InvalidArgumentException
     */
    public function setObject(mixed $object): static
    {
        if (!is_object(value: $object)) {
            throw new Exception\InvalidArgumentException(message: sprintf(
                '%s expects an object argument; received "%s"',
                __METHOD__,
                get_debug_type(value: $object),
            ));
        }

        $this->object = $object;
        return $this;
    }

    /**
     * Get the object used by the hydrator
     *
     * @return mixed
     */
    public function getObject(): mixed
    {
        return $this->object;
    }

    /**
     * Set the class or interface of objects that can be bound to this fieldset.
     *
     * @param null|class-string $allowObjectBindingClass
     */
    public function setAllowedObjectBindingClass(?string $allowObjectBindingClass): void
    {
        $this->allowedObjectBindingClass = $allowObjectBindingClass;
    }

    /**
     * Get The class or interface of objects that can be bound to this fieldset.
     *
     * @return null|class-string
     */
    public function allowedObjectBindingClass(): ?string
    {
        return $this->allowedObjectBindingClass;
    }

    /**
     * Checks if the object can be set in this fieldset
     *
     * @param object|array $object
     */
    public function allowObjectBinding($object): bool
    {
        $validBindingClass = false;
        $allowedObjectBindingClass = $this->allowedObjectBindingClass();
        if (is_object(value: $object) && $allowedObjectBindingClass !== null) {
            $objectClass = ltrim(string: $allowedObjectBindingClass, characters: '\\');
            $reflection = new ReflectionClass(objectOrClass: $object);
            $validBindingClass =
                $reflection->getName() === $objectClass || $reflection->isSubclassOf(class: $allowedObjectBindingClass);
        }

        return $validBindingClass || $this->object && $object instanceof $this->object;
    }

    /**
     * Set the hydrator to use when binding an object to the element
     *
     * @return static
     */
    public function setHydrator(HydratorInterface $hydrator): static
    {
        $this->hydrator = $hydrator;
        return $this;
    }

    /**
     * Set the hydrator by name to use when binding an object to the element.
     *
     * The form element manager {@see FormElementManager} is used via the
     * form factory {@see Factory} to fetch the hydrator.
     *
     * @throws Exception\DomainException If hydrator is not found in hydrator
     *                                   manager or service manager.
     */
    public function setHydratorByName(string $hydratorName): void
    {
        $this->setHydrator(hydrator: Value::instance(
            $this->getFormFactory()->getFormElementManager()->getHydratorFromName(hydratorName: $hydratorName),
            \Laminas\Hydrator\HydratorInterface::class,
        ));
    }

    /**
     * Get the hydrator used when binding an object to the fieldset
     *
     * If no hydrator is present and object implements HydratorAwareInterface,
     * hydrator will be retrieved from the object.
     *
     * Will lazy-load Hydrator\ArraySerializable if none is present.
     */
    public function getHydrator(): HydratorInterface
    {
        if (!$this->hydrator instanceof HydratorInterface) {
            if ($this->object instanceof HydratorAwareInterface) {
                $hydrator = $this->object->getHydrator();
                assert(assertion: $hydrator !== null);
                $this->setHydrator(hydrator: $hydrator);
            } else {
                $this->setHydrator(hydrator: new Hydrator\ArraySerializableHydrator());
            }
        }
        return Value::instance($this->hydrator, \Laminas\Hydrator\HydratorInterface::class);
    }

    /**
     * Checks if this fieldset can bind data
     */
    public function allowValueBinding(): bool
    {
        return is_object(value: $this->object);
    }

    /**
     * Bind values to the bound object
     *
     * @param  array|null $validationGroup
     * @return mixed
     */
    public function bindValues(array $values = [], ?array $validationGroup = null): mixed
    {
        $objectData = $this->extract();
        $hydrator = $this->getHydrator();
        $hydratableData = [];

        foreach ($this->iterator as $name => $element) {
            if (
                $validationGroup
                && (
                    !array_key_exists(key: $name, array: $validationGroup)
                    && !in_array(needle: $name, haystack: $validationGroup, strict: true)
                )
            ) {
                continue;
            }

            if (!array_key_exists(key: $name, array: $values)) {
                if (!$element instanceof CollectionInterface) {
                    continue;
                }

                $values[$name] = [];
            }

            /** @var mixed $value */
            $value = $values[$name];

            if ($element instanceof FieldsetInterface && $element->allowValueBinding()) {
                /** @var mixed $value */
                $value = $element->bindValues(
                    Value::array($value),
                    Value::nullableArray(empty($validationGroup[$name]) ? null : $validationGroup[$name]),
                );
            }

            // skip post values for disabled elements, get old value from object
            if (!$element->getAttribute(key: 'disabled')) {
                $hydratableData[$name] = $value;
            } elseif (array_key_exists(key: $name, array: $objectData)) {
                $hydratableData[$name] = $objectData[$name];
            }
        }

        if ($hydratableData !== [] && is_object($this->object)) {
            $this->object = $hydrator->hydrate(data: $hydratableData, object: $this->object);
        }

        return $this->object;
    }

    /**
     * Set if this fieldset is used as a base fieldset
     *
     * @return static
     */
    public function setUseAsBaseFieldset(bool $useAsBaseFieldset): static
    {
        $this->useAsBaseFieldset = $useAsBaseFieldset;
        return $this;
    }

    /**
     * Is this fieldset use as a base fieldset for a form ?
     */
    public function useAsBaseFieldset(): bool
    {
        return $this->useAsBaseFieldset;
    }

    /**
     * Extract values from the bound object
     */
    protected function extract(): array
    {
        if (!is_object(value: $this->object)) {
            return [];
        }

        $values = $this->getHydrator()->extract(object: Value::object($this->object));

        // Recursively extract and populate values for nested fieldsets
        foreach ($this->fieldsets as $fieldset) {
            $name = (string) $fieldset->getName();

            if (isset($values[$name])) {
                $object = $values[$name];

                if (
                    $fieldset instanceof Fieldset
                    && $fieldset->allowObjectBinding(object: Value::arrayOrObject($object))
                ) {
                    $fieldset->setObject(object: $object);
                    $values[$name] = $fieldset->extract();
                }
            }
        }

        return $values;
    }

    /**
     * Make a deep clone of a fieldset
     *
     * @return void
     */
    public function __clone()
    {
        $items = $this->iterator->toArray(flag: PriorityList::EXTR_BOTH);

        $this->elements = [];
        $this->fieldsets = [];
        $this->iterator = new PriorityList();
        $this->iterator->isLIFO(flag: false);

        /** @var mixed $item */
        foreach ($items as $name => $item) {
            /** @var mixed $elementOrFieldset */
            $elementOrFieldset = clone Value::instance(
                Value::accessible($item)['data'],
                ElementInterface::class,
            );

            $this->iterator->insert(
                name: (string) $name,
                value: $elementOrFieldset,
                priority: Value::int(Value::accessible($item)['priority']),
            );

            if ($elementOrFieldset instanceof FieldsetInterface) {
                $this->fieldsets[$name] = $elementOrFieldset;
            } elseif ($elementOrFieldset instanceof ElementInterface) {
                $this->elements[$name] = $elementOrFieldset;
            }
        }
        $this->iterator->rewind();
        // Also make a deep copy of the object in case it's used within a collection
        if (is_object(value: $this->object)) {
            $this->object = clone $this->object;
        }
    }
}
