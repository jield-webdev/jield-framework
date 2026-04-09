<?php

declare(strict_types=1);

namespace Jield\Form;

use Jield\Stdlib\ArrayUtils;
use Jield\Stdlib\InitializableInterface;
use Traversable;

use function array_key_exists;
use function assert;
use function is_string;

class Element implements
    ElementAttributeRemovalInterface,
    ElementInterface,
    InitializableInterface,
    LabelAwareInterface
{
    /** @var array<string, scalar|null>  */
    protected array $attributes = [];

    /** @var null|string */
    protected ?string $label;

    /** @var array<string, scalar|null> */
    protected array $labelAttributes = [];

    /**
     * Label specific options
     *
     * @var array<string, mixed>
     */
    protected array $labelOptions = [];

    /** @var array Validation error messages */
    protected array $messages = [];

    /** @var array custom options */
    protected array $options = [];

    /** @var mixed */
    protected mixed $value;

    /** @var boolean */
    protected bool $hasValue = false;

    /**
     * @param int|string|null $name    Optional name for the element
     * @param  iterable $options Optional options for the element
     * @throws Exception\InvalidArgumentException
     */
    public function __construct(int|string|null $name = null, iterable $options = [])
    {
        if (null !== $name) {
            $this->setName(name: (string) $name);
        }

        if (! empty($options)) {
            $this->setOptions(options: $options);
        }
    }

    /**
     * This function is automatically called when creating element with factory. It
     * allows to perform various operations (add elements...)
     *
     * @return void
     */
    public function init(): void
    {
    }

    /** @inheritDoc */
    public function setName(string $name): ElementInterface|static
    {
        $this->setAttribute(key: 'name', value: $name);
        return $this;
    }

    /** @inheritDoc */
    public function getName(): ?string
    {
        $name = $this->getAttribute(key: 'name');
        assert(assertion: is_string(value: $name) || $name === null);

        return $name;
    }

    /**
     * Set options for an element. Accepted options are:
     * - label: label to associate with the element
     * - label_attributes: attributes to use when the label is rendered
     * - label_options: label specific options
     *
     * @return self
     * @throws Exception\InvalidArgumentException
     */
    public function setOptions(iterable $options): static
    {
        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray(iterator: $options);
        }

        if (isset($options['label'])) {
            $this->setLabel(label: $options['label']);
        }

        if (isset($options['label_attributes'])) {
            $this->setLabelAttributes(labelAttributes: $options['label_attributes']);
        }

        if (isset($options['label_options'])) {
            $this->setLabelOptions(arrayOrTraversable: $options['label_options']);
        }

        $this->options = $options;

        return $this;
    }

    /**
     * Get defined options
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Return the specified option
     *
     * @return null|mixed
     */
    public function getOption(string $option): mixed
    {
        if (! isset($this->options[$option])) {
            return null;
        }

        return $this->options[$option];
    }

    /**
     * Set a single option for an element
     *
     * @param  mixed $value
     * @return $this
     */
    public function setOption(string $key, mixed $value): static
    {
        $this->options[$key] = $value;
        return $this;
    }

    /** @inheritDoc */
    public function setAttribute(string $key, $value): ElementInterface|static
    {
        // Do not include the value in the list of attributes
        if ($key === 'value') {
            $this->setValue(value: $value);
            return $this;
        }
        $this->attributes[$key] = $value;
        return $this;
    }

    /** @inheritDoc */
    public function getAttribute(string $key): float|bool|int|string|null
    {
        if (! isset($this->attributes[$key])) {
            return null;
        }

        return $this->attributes[$key];
    }

    /**
     * Remove a single attribute
     *
     * @return $this
     */
    public function removeAttribute(string $key): static
    {
        unset($this->attributes[$key]);
        return $this;
    }

    /**
     * Does the element has a specific attribute ?
     */
    public function hasAttribute(string $key): bool
    {
        return array_key_exists(key: $key, array: $this->attributes);
    }

    /**
     * @inheritDoc
     * @throws Exception\InvalidArgumentException
     */
    public function setAttributes(iterable $arrayOrTraversable): ElementInterface|static
    {
        foreach ($arrayOrTraversable as $key => $value) {
            $this->setAttribute(key: $key, value: $value);
        }
        return $this;
    }

    /** @inheritDoc */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Remove many attributes at once
     *
     * @param list<string> $keys
     * @return $this
     */
    public function removeAttributes(array $keys): static
    {
        foreach ($keys as $key) {
            unset($this->attributes[$key]);
        }

        return $this;
    }

    /**
     * Clear all attributes
     *
     * @return $this
     */
    public function clearAttributes(): static
    {
        $this->attributes = [];
        return $this;
    }

    /** @inheritDoc */
    public function setValue(mixed $value): ElementInterface|static
    {
        $this->value    = $value;
        $this->hasValue = true;

        return $this;
    }

    /** @inheritDoc */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /** @inheritDoc */
    public function setLabel(?string $label): ElementInterface
    {
        if (is_string(value: $label)) {
            $this->label = $label;
        }

        return $this;
    }

    /** @inheritDoc */
    public function getLabel(): ?string
    {
        return $this->label;
    }

    /** @inheritDoc */
    public function setLabelAttributes(array $labelAttributes): LabelAwareInterface|static
    {
        $this->labelAttributes = $labelAttributes;
        return $this;
    }

    /** @inheritDoc */
    public function getLabelAttributes(): array
    {
        return $this->labelAttributes;
    }

    /**
     * @inheritDoc
     * @throws Exception\InvalidArgumentException
     */
    public function setLabelOptions(iterable $arrayOrTraversable): LabelAwareInterface|static
    {
        foreach ($arrayOrTraversable as $key => $value) {
            $this->setLabelOption(key: $key, value: $value);
        }
        return $this;
    }

    /** @inheritDoc */
    public function getLabelOptions(): array
    {
        return $this->labelOptions;
    }

    /** @inheritDoc */
    public function clearLabelOptions(): LabelAwareInterface|static
    {
        $this->labelOptions = [];
        return $this;
    }

    /** @inheritDoc */
    public function removeLabelOptions(array $keys): LabelAwareInterface|static
    {
        foreach ($keys as $key) {
            unset($this->labelOptions[$key]);
        }

        return $this;
    }

    /** @inheritDoc */
    public function setLabelOption(string $key, mixed $value): LabelAwareInterface|static
    {
        $this->labelOptions[$key] = $value;
        return $this;
    }

    /** @inheritDoc */
    public function getLabelOption(int|string $key): mixed
    {
        if (! isset($this->labelOptions[$key])) {
            return null;
        }

        return $this->labelOptions[$key];
    }

    /** @inheritDoc */
    public function removeLabelOption(string $key): LabelAwareInterface|static
    {
        unset($this->labelOptions[$key]);
        return $this;
    }

    /** @inheritDoc */
    public function hasLabelOption(string $key): bool
    {
        return array_key_exists(key: $key, array: $this->labelOptions);
    }

    /** @inheritDoc */
    public function setMessages(iterable $messages): ElementInterface|static
    {
        if ($messages instanceof Traversable) {
            $messages = ArrayUtils::iteratorToArray(iterator: $messages);
        }
        $this->messages = $messages;
        return $this;
    }

    /** @inheritDoc */
    public function getMessages(): array
    {
        return $this->messages;
    }

    public function hasValue(): bool
    {
        return $this->hasValue;
    }
}
