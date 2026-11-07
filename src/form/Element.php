<?php

declare(strict_types=1);

namespace Jield\Form;

use Laminas\Stdlib\ArrayUtils;
use Laminas\Stdlib\InitializableInterface;
use Traversable;

use function array_key_exists;
use function assert;
use function is_string;

class Element implements ElementAttributeRemovalInterface, ElementInterface, InitializableInterface, LabelAwareInterface
{
    use LabelAwareTrait;

    /** @var array<string, scalar|null>  */
    protected array $attributes = [];

    /** @var null|string */
    protected ?string $label = null;

    /** @var array Validation error messages */
    protected array $messages = [];

    /** @var array custom options */
    protected array $options = [];

    /** @var mixed */
    protected mixed $value = null;

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

        if (!empty($options)) {
            $this->setOptions(options: $options);
        }
    }

    /**
     * This function is automatically called when creating element with factory. It
     * allows to perform various operations (add elements...)
     *
     * @return void
     */
    public function init(): void {}

    /** @inheritDoc */
    public function setName(string $name): static
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
     * @return static
     * @throws Exception\InvalidArgumentException
     */
    public function setOptions(iterable $options): static
    {
        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray(iterator: $options);
        }

        if (isset($options['label'])) {
            $this->setLabel(label: \Webmozart\Assert\Assert::nullOrString($options['label']));
        }

        if (isset($options['label_attributes'])) {
            $this->setLabelAttributes(labelAttributes: \Webmozart\Assert\Assert::isArray($options['label_attributes']));
        }

        if (isset($options['label_options'])) {
            $this->setLabelOptions(arrayOrTraversable: \Webmozart\Assert\Assert::isIterable($options['label_options']));
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
        if (!isset($this->options[$option])) {
            return null;
        }

        return $this->options[$option];
    }

    /**
     * Set a single option for an element
     *
     * @param  mixed $value
     * @return static
     */
    public function setOption(string $key, mixed $value): static
    {
        $this->options[$key] = $value;
        return $this;
    }

    /** @inheritDoc */
    public function setAttribute(string $key, $value): static
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
    public function getAttribute(string $key): mixed
    {
        if (!isset($this->attributes[$key])) {
            return null;
        }

        return $this->attributes[$key];
    }

    /**
     * Remove a single attribute
     *
     * @return static
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
    public function setAttributes(iterable $arrayOrTraversable): static
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
     * @return static
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
     * @return static
     */
    public function clearAttributes(): static
    {
        $this->attributes = [];
        return $this;
    }

    /** @inheritDoc */
    public function setValue(mixed $value): static
    {
        $this->value = $value;
        $this->hasValue = true;

        return $this;
    }

    /** @inheritDoc */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /** @inheritDoc */
    public function setLabel(?string $label): static
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
    public function getLabelOption(int|string $key): mixed
    {
        if (!isset($this->labelOptions[$key])) {
            return null;
        }

        return $this->labelOptions[$key];
    }

    /** @inheritDoc */
    public function setMessages(iterable $messages): static
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
