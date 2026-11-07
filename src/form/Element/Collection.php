<?php

declare(strict_types=1);

namespace Jield\Form\Element;

use Jield\Form\ElementInterface;
use Jield\Form\Exception;
use Jield\Form\Fieldset;
use Jield\Form\FieldsetInterface;
use Jield\Form\FormInterface;
use Jield\Form\InputFilterProviderFieldset;
use Jield\Stdlib\Value;
use Laminas\Stdlib\ArrayUtils;
use Laminas\Stdlib\Exception\InvalidArgumentException;
use Traversable;
use Webmozart\Assert\Assert;

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
// Preserve the existing public API and the state and operations that implement it.
// @mago-expect lint:cyclomatic-complexity(1)
// @mago-expect lint:kan-defect(1)
// @mago-expect lint:too-many-methods(1)
class Collection extends Fieldset implements CollectionInterface
{
    /**
     * @deprecated Please use the constant CollectionInterface::DEFAULT_TEMPLATE_PLACEHOLDER instead
     */
    public const string DEFAULT_TEMPLATE_PLACEHOLDER = '__index__';

    /**
     * Element used in the collection
     */
    protected ?ElementInterface $targetElement = null;

    /**
     * Initial count of target element
     */
    protected int $count = 1;

    /**
     * Are new elements allowed to be added dynamically ?
     */
    protected bool $allowAdd = true;

    /**
     * Are existing elements allowed to be removed dynamically ?
     */
    protected bool $allowRemove = true;

    /**
     * Is the template generated ?
     */
    protected bool $shouldCreateTemplate = false;

    /**
     * Placeholder used in template content for making your life easier with JavaScript
     */
    protected string $templatePlaceholder = CollectionInterface::DEFAULT_TEMPLATE_PLACEHOLDER;

    /**
     * Whether or not to create new objects during modify
     */
    protected bool $createNewObjects = false;

    /**
     * Element used as a template
     */
    protected ElementInterface|FieldsetInterface|null $templateElement = null;

    /**
     * The index of the last child element or fieldset
     */
    protected int $lastChildIndex = -1;

    /**
     * Should child elements must be created on self::prepareElement()?
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
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws Exception\ExceptionInterface
     * @throws \Laminas\InputFilter\Exception\RuntimeException
     * @throws \ReflectionException
     */
    public function setOptions(iterable $options): static
    {
        parent::setOptions(options: $options);

        if (($this->options['target_element'] ?? null) !== null) {
            /** @mago-var mixed $target */
            $target = $this->options['target_element'];
            $this->setTargetElement(
                elementOrFieldset: is_iterable($target)
                    ? $target
                    : Assert::isInstanceOf($target, ElementInterface::class),
            );
        }

        if (($this->options['count'] ?? null) !== null) {
            $this->setCount(count: Assert::integer($this->options['count']));
        }

        if (($this->options['allow_add'] ?? null) !== null) {
            $this->setAllowAdd(allowAdd: Assert::boolean($this->options['allow_add']));
        }

        if (($this->options['allow_remove'] ?? null) !== null) {
            $this->setAllowRemove(allowRemove: Assert::boolean($this->options['allow_remove']));
        }

        if (($this->options['should_create_template'] ?? null) !== null) {
            $this->setShouldCreateTemplate(shouldCreateTemplate: Assert::boolean(
                $this->options['should_create_template'],
            ));
        }

        if (($this->options['template_placeholder'] ?? null) !== null) {
            $this->setTemplatePlaceholder(templatePlaceholder: Assert::string($this->options['template_placeholder']));
        }

        if (($this->options['create_new_objects'] ?? null) !== null) {
            $this->setCreateNewObjects(createNewObjects: Assert::boolean($this->options['create_new_objects']));
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
     * @throws Exception\InvalidArgumentException
     */
    public function setObject(mixed $object): static
    {
        // These alternatives are mutually exclusive; the following code consumes the selected state.
        // @mago-expect lint:no-else-clause(1)
        if ($object instanceof Traversable) {
            $object = iterator_to_array(iterator: $object);
        } elseif (!is_array(value: $object)) {
            throw new Exception\InvalidArgumentException(message: sprintf(
                '%s expects an array or Traversable object argument; received "%s"',
                __METHOD__,
                get_debug_type(value: $object),
            ));
        }

        $this->object = $object;
        $this->count = max(count(value: $object), $this->count);

        return $this;
    }

    /**
     * Populate values
     *
     * @throws Exception\ExceptionInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \ReflectionException
     * @throws \Laminas\InputFilter\Exception\RuntimeException
     */
    public function populateValues(iterable $data): void
    {
        if ($data instanceof Traversable) {
            $data = ArrayUtils::iteratorToArray(iterator: $data);
        }

        if (!$this->allowRemove && count(value: $data) < $this->count) {
            throw new Exception\DomainException(message: sprintf(
                'There are fewer elements than specified in the collection (%s). Either set the allow_remove option '
                . 'to true, or re-submit the form.',
                static::class,
            ));
        }

        // Check to see if elements have been replaced or removed
        $toRemove = [];
        foreach ($this as $name => $elementOrFieldset) {
            if (($data[$name] ?? null) !== null) {
                continue;
            }

            if (!$this->allowRemove) {
                throw new Exception\DomainException(message: sprintf(
                    'Elements have been removed from the collection (%s) but the allow_remove option is not true.',
                    static::class,
                ));
            }

            $toRemove[] = $name;
        }

        foreach ($toRemove as $name) {
            $this->remove(elementOrFieldset: $name);
        }

        /** @mago-var mixed $value */
        foreach ($data as $key => $value) {
            $elementOrFieldset = null;
            // These alternatives are mutually exclusive; the following code consumes the selected state.
            // @mago-expect lint:no-else-clause(1)
            if ($this->has(elementOrFieldset: (string) $key)) {
                $elementOrFieldset = $this->get(elementOrFieldset: (string) $key);
            } elseif ($this->targetElement) {
                $elementOrFieldset = $this->addNewTargetElementInstance(name: (string) $key);

                if (is_int(value: $key) && $key > $this->lastChildIndex) {
                    $this->lastChildIndex = $key;
                }
            }

            if ($elementOrFieldset instanceof FieldsetInterface) {
                $elementOrFieldset->populateValues(data: Assert::isIterable($value));
                continue;
            }

            if ($elementOrFieldset !== null) {
                $elementOrFieldset->setAttribute(key: 'value', value: Value::plainScalar($value));
            }
        }

        if (!$this->createNewObjects()) {
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
     * @return list<mixed> Values bound from the collection's elements and fieldsets.
     */
    public function bindValues(array $values = [], ?array $validationGroup = null): mixed
    {
        $collection = [];
        /** @mago-var mixed $value */
        foreach ($values as $name => $value) {
            $element = $this->get(elementOrFieldset: (string) $name);

            $collection[] = $element instanceof FieldsetInterface
                ? $element->bindValues(Assert::isArray($value), $validationGroup)
                : $value;
        }

        return $collection;
    }

    /**
     * Set the initial count of target element
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
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws Exception\ExceptionInterface
     * @throws \Laminas\InputFilter\Exception\RuntimeException
     * @throws \ReflectionException
     */
    public function setTargetElement(Traversable|ElementInterface|array $elementOrFieldset): static
    {
        if (
            is_array(value: $elementOrFieldset)
            || $elementOrFieldset instanceof Traversable
            && !$elementOrFieldset instanceof ElementInterface
        ) {
            $factory = $this->getFormFactory();
            $elementOrFieldset = $factory->create(spec: $elementOrFieldset);
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
     * @throws \ReflectionException
     */
    public function getTemplateElement(): FieldsetInterface|ElementInterface|null
    {
        $this->templateElement ??= $this->createTemplateElement();

        return $this->templateElement;
    }

    /**
     * Prepare the collection by adding a dummy template element if the user want one
     *
     * @throws Exception\ExceptionInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \ReflectionException
     * @throws \Laminas\InputFilter\Exception\RuntimeException
     */
    public function prepareElement(FormInterface $form): void
    {
        if (true === $this->shouldCreateChildrenOnPrepareElement && $this->targetElement !== null && $this->count > 0) {
            while ($this->count > ($this->lastChildIndex + 1)) {
                $this->addNewTargetElementInstance(name: (string) ++$this->lastChildIndex);
            }
        }

        // Create a template that will also be prepared
        if ($this->shouldCreateTemplate) {
            $templateElement = $this->getTemplateElement();
            $this->add(elementOrFieldset: Assert::notNull($templateElement));
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
     * @throws \ReflectionException
     */
    // Keep the coordinated parsing or rendering steps of this compatibility method together.
    // @mago-expect lint:halstead(1)
    public function extract(): array
    {
        $objects = $this->object;
        // These alternatives are mutually exclusive; the following code consumes the selected state.
        // @mago-expect lint:no-else-clause(1)
        if ($objects instanceof Traversable) {
            $objects = ArrayUtils::iteratorToArray(iterator: $objects, recursive: false);
            $this->object = $objects;
        } elseif (!is_array(value: $objects)) {
            return [];
        }

        $values = [];

        /** @mago-var mixed $value */
        foreach ($objects as $key => $value) {
            // If a hydrator is provided, our work here is done
            if ($this->hydrator) {
                $values[$key] = $this->hydrator->extract(object: Assert::object($value));
                continue;
            }

            // If the target element is a fieldset that can accept the provided value
            // we should clone it, inject the value and extract the data
            if ($this->targetElement instanceof FieldsetInterface) {
                if (!$this->targetElement->allowObjectBinding(object: Assert::object($value))) {
                    continue;
                }
                $targetElement = clone $this->targetElement;
                assert(
                    assertion: $targetElement instanceof Fieldset,
                    description: 'Expected $targetElement instanceof Fieldset.',
                );
                $targetElement->setObject(object: $value);
                $values[$key] = $targetElement->extract();
                if (!$this->createNewObjects() && $this->has(elementOrFieldset: (string) $key)) {
                    $fieldset = $this->get(elementOrFieldset: (string) $key);
                    assert(
                        assertion: $fieldset instanceof FieldsetInterface,
                        description: 'Expected $fieldset instanceof FieldsetInterface.',
                    );
                    $fieldset->setObject(object: $value);
                }
                continue;
            }

            // If the target element is a non-fieldset element, just use the value
            if ($this->targetElement instanceof ElementInterface) {
                $values[$key] = $value;
                if (!$this->createNewObjects() && $this->has(elementOrFieldset: (string) $key)) {
                    $this->get(elementOrFieldset: (string) $key)->setValue(value: $value);
                }
                continue;
            }
        }

        return $values;
    }

    /**
     * Create a new instance of the target element
     *
     * @throws \ReflectionException
     */
    protected function createNewTargetElementInstance(): ElementInterface
    {
        assert(assertion: $this->targetElement !== null, description: 'Expected $this->targetElement !== null.');
        /** @mago-var mixed $type */
        if (
            $this->targetElement instanceof InputFilterProviderFieldset
            && null !== ($type = $this->targetElement->getOption(option: 'target_type'))
        ) {
            assert(
                assertion: is_string(value: $type) && class_exists(class: $type),
                description: 'Expected is_string($type) && class_exists($type).',
            );
            $this->targetElement->setObject(object: Value::instantiate($type));
        }
        return clone $this->targetElement;
    }

    /**
     * Add a new instance of the target element
     *
     * @throws Exception\ExceptionInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \ReflectionException
     * @throws \Laminas\InputFilter\Exception\RuntimeException
     */
    protected function addNewTargetElementInstance(string $name): ElementInterface
    {
        $this->shouldCreateChildrenOnPrepareElement = false;

        $elementOrFieldset = $this->createNewTargetElementInstance();
        $elementOrFieldset->setName(name: $name);

        $this->add(elementOrFieldset: $elementOrFieldset);

        if (!$this->allowAdd && $this->count() > $this->count) {
            throw new Exception\DomainException(message: sprintf(
                'There are more elements than specified in the collection (%s). Either set the allow_add option '
                . 'to true, or re-submit the form.',
                static::class,
            ));
        }

        return $elementOrFieldset;
    }

    /**
     * Create a dummy template element
     *
     * @throws \ReflectionException
     */
    protected function createTemplateElement(): FieldsetInterface|ElementInterface|null
    {
        if (!$this->shouldCreateTemplate) {
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

        if (!count(value: $fieldsets) || !$this->object) {
            return;
        }

        foreach ($fieldsets as $fieldset) {
            $i = (string) $fieldset->getName();
            if ($i !== '' && ($this->object[$i] ?? null) !== null) {
                $fieldset->setObject(object: $this->object[$i]);
            }
        }
    }
}
