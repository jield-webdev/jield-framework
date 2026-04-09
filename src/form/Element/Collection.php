<?php

declare(strict_types=1);

namespace Jield\Form\Element;

use ArrayAccess;
use Jield\Form\ElementInterface;
use Jield\Form\Exception;
use Jield\Form\Fieldset;
use Jield\Form\FieldsetInterface;
use Jield\Form\FormInterface;
use Jield\Form\InputFilterProviderFieldset;
use Jield\Stdlib\ArrayUtils;
use Jield\Stdlib\Exception\InvalidArgumentException;
use Traversable;

use function assert;
use function class_exists;
use function count;
use function get_debug_type;
use function is_array;
use function is_int;
use function is_string;
use function iterator_to_array;
use function max;
use function sprintf;

/** @final */
class Collection extends Fieldset implements CollectionInterface
{
    /**
     * @deprecated Please use the constant CollectionInterface::DEFAULT_TEMPLATE_PLACEHOLDER instead
     */
    public const string DEFAULT_TEMPLATE_PLACEHOLDER = '__index__';

    /** @var array|ArrayAccess */
    protected ?object $object;

    /**
     * Element used in the collection
     *
     * @var null|ElementInterface
     */
    protected ?ElementInterface $targetElement;

    /**
     * Initial count of target element
     *
     * @var int
     */
    protected int $count = 1;

    /**
     * Are new elements allowed to be added dynamically ?
     *
     * @var bool
     */
    protected bool $allowAdd = true;

    /**
     * Are existing elements allowed to be removed dynamically ?
     *
     * @var bool
     */
    protected bool $allowRemove = true;

    /**
     * Is the template generated ?
     *
     * @var bool
     */
    protected bool $shouldCreateTemplate = false;

    /**
     * Placeholder used in template content for making your life easier with JavaScript
     *
     * @var string
     */
    protected string $templatePlaceholder = CollectionInterface::DEFAULT_TEMPLATE_PLACEHOLDER;

    /**
     * Whether or not to create new objects during modify
     *
     * @var bool
     */
    protected bool $createNewObjects = false;

    /**
     * Element used as a template
     *
     * @var null|ElementInterface|FieldsetInterface
     */
    protected ElementInterface|null|FieldsetInterface $templateElement;

    /**
     * The index of the last child element or fieldset
     *
     * @var int
     */
    protected int $lastChildIndex = -1;

    /**
     * Should child elements must be created on self::prepareElement()?
     *
     * @var bool
     */
    protected bool $shouldCreateChildrenOnPrepareElement = true;

    /**
     * Accepted options for Collection:
     * - target_element: an array or element used in the collection
     * - count: number of times the element is added initially
     * - allow_add: if set to true, elements can be added to the form dynamically (using JavaScript)
     * - allow_remove: if set to true, elements can be removed to the form
     * - should_create_template: if set to true, a template is generated (inside a <span>)
     * - template_placeholder: placeholder used in the data template
     *
     * @return $this
     */
    public function setOptions(iterable $options): static
    {
        parent::setOptions(options: $options);

        if (isset($this->options['target_element'])) {
            $this->setTargetElement(elementOrFieldset: $this->options['target_element']);
        }

        if (isset($this->options['count'])) {
            $this->setCount(count: $this->options['count']);
        }

        if (isset($this->options['allow_add'])) {
            $this->setAllowAdd(allowAdd: $this->options['allow_add']);
        }

        if (isset($this->options['allow_remove'])) {
            $this->setAllowRemove(allowRemove: $this->options['allow_remove']);
        }

        if (isset($this->options['should_create_template'])) {
            $this->setShouldCreateTemplate(shouldCreateTemplate: $this->options['should_create_template']);
        }

        if (isset($this->options['template_placeholder'])) {
            $this->setTemplatePlaceholder(templatePlaceholder: $this->options['template_placeholder']);
        }

        if (isset($this->options['create_new_objects'])) {
            $this->setCreateNewObjects(createNewObjects: $this->options['create_new_objects']);
        }

        return $this;
    }

    /**
     * Checks if the object can be set in this fieldset
     *
     * @param object|array $object
     */
    public function allowObjectBinding($object): bool
    {
        return true;
    }

    /**
     * Set the object used by the hydrator
     * In this case the "object" is a collection of objects
     *
     * @param mixed $object
     * @return self
     * @throws Exception\InvalidArgumentException
     */
    public function setObject(mixed $object): static
    {
        if ($object instanceof Traversable) {
            $object = iterator_to_array(iterator: $object);
        } elseif (! is_array(value: $object)) {
            throw new Exception\InvalidArgumentException(message: sprintf(
                '%s expects an array or Traversable object argument; received "%s"',
                __METHOD__,
                get_debug_type(value: $object),
            ));
        }

        $this->object = $object;
        $this->count  = max(count(value: $object), $this->count);

        return $this;
    }

    /**
     * Populate values
     *
     * @throws Exception\InvalidArgumentException
     * @throws Exception\DomainException
     */
    public function populateValues(iterable $data): void
    {
        if ($data instanceof Traversable) {
            $data = ArrayUtils::iteratorToArray(iterator: $data);
        }

        if (! $this->allowRemove && count(value: $data) < $this->count) {
            throw new Exception\DomainException(message: sprintf(
                'There are fewer elements than specified in the collection (%s). Either set the allow_remove option '
                . 'to true, or re-submit the form.',
                static::class
            ));
        }

        // Check to see if elements have been replaced or removed
        $toRemove = [];
        foreach ($this as $name => $elementOrFieldset) {
            if (isset($data[$name])) {
                continue;
            }

            if (! $this->allowRemove) {
                throw new Exception\DomainException(message: sprintf(
                    'Elements have been removed from the collection (%s) but the allow_remove option is not true.',
                    static::class
                ));
            }

            $toRemove[] = $name;
        }

        foreach ($toRemove as $name) {
            $this->remove(elementOrFieldset: (string) $name);
        }

        foreach ($data as $key => $value) {
            $elementOrFieldset = null;
            if ($this->has(elementOrFieldset: (string) $key)) {
                $elementOrFieldset = $this->get(elementOrFieldset: (string) $key);
            } elseif ($this->targetElement) {
                $elementOrFieldset = $this->addNewTargetElementInstance(name: (string) $key);

                if (is_int(value: $key) && $key > $this->lastChildIndex) {
                    $this->lastChildIndex = $key;
                }
            }

            if ($elementOrFieldset instanceof FieldsetInterface) {
                $elementOrFieldset->populateValues(data: $value);
                continue;
            }

            if ($elementOrFieldset !== null) {
                $elementOrFieldset->setAttribute(key: 'value', value: $value);
            }
        }

        if (! $this->createNewObjects()) {
            $this->replaceTemplateObjects();
        }
    }

    /**
     * Checks if this fieldset can bind data
     */
    public function allowValueBinding(): bool
    {
        return true;
    }

    /**
     * Bind values to the object
     *
     * @return array|mixed|void
     */
    public function bindValues(array $values = [], ?array $validationGroup = null): mixed
    {
        $collection = [];
        foreach ($values as $name => $value) {
            $element = $this->get(elementOrFieldset: (string) $name);

            if ($element instanceof FieldsetInterface) {
                $collection[] = $element->bindValues($value, $validationGroup);
            } else {
                $collection[] = $value;
            }
        }

        return $collection;
    }

    /**
     * Set the initial count of target element
     *
     * @return self
     */
    public function setCount(int $count): static
    {
        $this->count = $count > 0 ? $count : 0;
        return $this;
    }

    /**
     * Get the initial count of target element
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * Set the target element
     *
     * @param Traversable|array|ElementInterface $elementOrFieldset
     * @return self
     * @throws Exception\InvalidArgumentException
     */
    public function setTargetElement(Traversable|ElementInterface|array $elementOrFieldset): static
    {
        if (
            is_array(value: $elementOrFieldset)
            || ($elementOrFieldset instanceof Traversable && ! $elementOrFieldset instanceof ElementInterface)
        ) {
            $factory           = $this->getFormFactory();
            $elementOrFieldset = $factory->create(spec: $elementOrFieldset);
        }

        if (! $elementOrFieldset instanceof ElementInterface) {
            throw new Exception\InvalidArgumentException(message: sprintf(
                '%s requires that $elementOrFieldset be an object implementing %s; received "%s"',
                __METHOD__,
                __NAMESPACE__ . '\ElementInterface',
                get_debug_type(value: $elementOrFieldset),
            ));
        }

        $this->targetElement = $elementOrFieldset;

        return $this;
    }

    /**
     * Get target element
     */
    public function getTargetElement(): ?ElementInterface
    {
        return $this->targetElement;
    }

    /**
     * @return self
     */
    public function setAllowAdd(bool $allowAdd): static
    {
        $this->allowAdd = $allowAdd;
        return $this;
    }

    /**
     * Get allow add
     */
    public function allowAdd(): bool
    {
        return $this->allowAdd;
    }

    /**
     * @return self
     */
    public function setAllowRemove(bool $allowRemove): static
    {
        $this->allowRemove = $allowRemove;
        return $this;
    }

    public function allowRemove(): bool
    {
        return $this->allowRemove;
    }

    /**
     * If set to true, a template prototype is automatically added to the form
     * to ease the creation of dynamic elements through JavaScript
     *
     * @return self
     */
    public function setShouldCreateTemplate(bool $shouldCreateTemplate): static
    {
        $this->shouldCreateTemplate = $shouldCreateTemplate;

        return $this;
    }

    /**
     * Get if the collection should create a template
     */
    public function shouldCreateTemplate(): bool
    {
        return $this->shouldCreateTemplate;
    }

    /**
     * Set the placeholder used in the template generated to help create new elements in JavaScript
     *
     * @return self
     */
    public function setTemplatePlaceholder(string $templatePlaceholder): static
    {
        $this->templatePlaceholder = $templatePlaceholder;

        return $this;
    }

    /**
     * Get the template placeholder
     */
    public function getTemplatePlaceholder(): string
    {
        return $this->templatePlaceholder;
    }

    /**
     * @return self
     */
    public function setCreateNewObjects(bool $createNewObjects): static
    {
        $this->createNewObjects = $createNewObjects;
        return $this;
    }

    public function createNewObjects(): bool
    {
        return $this->createNewObjects;
    }

    /**
     * Get a template element used for rendering purposes only
     *
     * @return null|ElementInterface|FieldsetInterface
     */
    public function getTemplateElement(): FieldsetInterface|ElementInterface|null
    {
        if ($this->templateElement === null) {
            $this->templateElement = $this->createTemplateElement();
        }

        return $this->templateElement;
    }

    /**
     * Prepare the collection by adding a dummy template element if the user want one
     */
    public function prepareElement(FormInterface $form): void
    {
        if ((true === $this->shouldCreateChildrenOnPrepareElement) && $this->targetElement !== null && $this->count > 0) {
            while ($this->count > $this->lastChildIndex + 1) {
                $this->addNewTargetElementInstance(name: (string) ++$this->lastChildIndex);
            }
        }

        // Create a template that will also be prepared
        if ($this->shouldCreateTemplate) {
            $templateElement = $this->getTemplateElement();
            $this->add(elementOrFieldset: $templateElement);
        }

        parent::prepareElement(form: $form);

        // The template element has been prepared, but we don't want it to be
        // rendered nor validated, so remove it from the list.
        if ($this->shouldCreateTemplate) {
            $this->remove(elementOrFieldset: $this->templatePlaceholder);
        }
    }

    /**
     * @throws Exception\InvalidArgumentException
     * @throws InvalidArgumentException
     * @throws Exception\DomainException
     * @throws Exception\InvalidElementException
     */
    public function extract(): array
    {
        if ($this->object instanceof Traversable) {
            $this->object = ArrayUtils::iteratorToArray(iterator: $this->object, recursive: false);
        } elseif (! is_array(value: $this->object)) {
            return [];
        }

        $values = [];

        foreach ($this->object as $key => $value) {
            // If a hydrator is provided, our work here is done
            if ($this->hydrator) {
                $values[$key] = $this->hydrator->extract(object: $value);
                continue;
            }

            // If the target element is a fieldset that can accept the provided value
            // we should clone it, inject the value and extract the data
            if ($this->targetElement instanceof FieldsetInterface) {
                if (! $this->targetElement->allowObjectBinding(object: $value)) {
                    continue;
                }
                $targetElement = clone $this->targetElement;
                assert(assertion: $targetElement instanceof Fieldset);
                $targetElement->setObject(object: $value);
                $values[$key] = $targetElement->extract();
                if (! $this->createNewObjects() && $this->has(elementOrFieldset: (string) $key)) {
                    $fieldset = $this->get(elementOrFieldset: (string) $key);
                    assert(assertion: $fieldset instanceof FieldsetInterface);
                    $fieldset->setObject(object: $value);
                }
                continue;
            }

            // If the target element is a non-fieldset element, just use the value
            if ($this->targetElement instanceof ElementInterface) {
                $values[$key] = $value;
                if (! $this->createNewObjects() && $this->has(elementOrFieldset: (string) $key)) {
                    $this->get(elementOrFieldset: (string) $key)->setValue(value: $value);
                }
                continue;
            }
        }

        return $values;
    }

    /**
     * Create a new instance of the target element
     */
    protected function createNewTargetElementInstance(): ElementInterface
    {
        assert(assertion: $this->targetElement !== null);
        if (($this->targetElement instanceof InputFilterProviderFieldset) && null !== ($type = $this->targetElement->getOption(option: 'target_type'))) {
            assert(assertion: is_string(value: $type) && class_exists(class: $type));
            $this->targetElement->setObject(object: new $type());
        }
        return clone $this->targetElement;
    }

    /**
     * Add a new instance of the target element
     *
     * @throws Exception\DomainException
     */
    protected function addNewTargetElementInstance(string $name): ElementInterface
    {
        $this->shouldCreateChildrenOnPrepareElement = false;

        $elementOrFieldset = $this->createNewTargetElementInstance();
        $elementOrFieldset->setName(name: $name);

        $this->add(elementOrFieldset: $elementOrFieldset);

        if (! $this->allowAdd && $this->count() > $this->count) {
            throw new Exception\DomainException(message: sprintf(
                'There are more elements than specified in the collection (%s). Either set the allow_add option '
                . 'to true, or re-submit the form.',
                static::class
            ));
        }

        return $elementOrFieldset;
    }

    /**
     * Create a dummy template element
     *
     * @return null|ElementInterface|FieldsetInterface
     */
    protected function createTemplateElement(): FieldsetInterface|ElementInterface|null
    {
        if (! $this->shouldCreateTemplate) {
            return null;
        }

        if ($this->templateElement) {
            return $this->templateElement;
        }

        $elementOrFieldset = $this->createNewTargetElementInstance();
        $elementOrFieldset->setName(name: $this->templatePlaceholder);

        return $elementOrFieldset;
    }

    /**
     * Replaces the default template object of a sub element with the corresponding
     * real entity so that all properties are preserved.
     */
    protected function replaceTemplateObjects(): void
    {
        $fieldsets = $this->getFieldsets();

        if (! count(value: $fieldsets) || ! $this->object) {
            return;
        }

        foreach ($fieldsets as $fieldset) {
            $i = (string) $fieldset->getName();
            if ($i !== '' && isset($this->object[$i])) {
                $fieldset->setObject(object: $this->object[$i]);
            }
        }
    }
}
