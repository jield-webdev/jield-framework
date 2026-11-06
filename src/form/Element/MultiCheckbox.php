<?php

declare(strict_types=1);

namespace Jield\Form\Element;

use Jield\Validator\Each;
use Laminas\Validator\InArray as InArrayValidator;
use Laminas\Validator\ValidatorInterface;

use function assert;
use function is_array;
use function is_iterable;
use function trigger_error;

use const E_USER_DEPRECATED;

/**
 * @psalm-type ValueOptionSpec = array<array-key, scalar|\Stringable|array{
 *     label: string, value: scalar|\Stringable, selected?: bool, disabled?: bool,
 *     attributes?: array<string, scalar|null>, label_attributes?: array<string, scalar|null>
 * }>
 */
class MultiCheckbox extends Checkbox
{
    /** @var array<string, scalar|null>  */
    protected array $attributes = [
        'type' => 'multi_checkbox',
    ];

    /** @var bool */
    protected bool $disableInArrayValidator = false;

    /** @var bool */
    protected bool $useHiddenElement = false;

    /** @var null|string */
    protected ?string $uncheckedValue = null;

    /** @var ValueOptionSpec */
    protected array $valueOptions = [];

    /**
     * @return ValueOptionSpec
     */
    public function getValueOptions(): array
    {
        return $this->valueOptions;
    }

    /**
     * @param ValueOptionSpec $options
     * @return static
     */
    public function setValueOptions(array $options): static
    {
        $this->valueOptions = $options;

        // Validators are immutable; rebuild them for the updated options.
        $this->validator = null;

        return $this;
    }

    /**
     * Unset a value option
     *
     * This method will only unset a value option when the element was created with a simple array of key-value pairs
     * for value options, for example ['value1' => 'label1', 'value2' => 'label2']
     *
     * @return static
     */
    public function unsetValueOption(string $key): static
    {
        if (isset($this->valueOptions[$key])) {
            unset($this->valueOptions[$key]);
            $this->validator = null;
        }

        return $this;
    }

    /**
     * Set options for an element. Accepted options are:
     * - label: label to associate with the element
     * - label_attributes: attributes to use when the label is rendered
     * - value_options: list of values and labels for the select options
     *
     * @inheritDoc
     */
    public function setOptions(iterable $options): static
    {
        parent::setOptions(options: $options);

        if (isset($this->options['value_options'])) {
            /** @var ValueOptionSpec $valueOptions */
            $valueOptions = $this->options['value_options'];
            $this->setValueOptions(options: $valueOptions);
        }
        // Alias for 'value_options'
        if (isset($this->options['options'])) {
            /** @var ValueOptionSpec $valueOptions */
            $valueOptions = $this->options['options'];
            $this->setValueOptions(options: $valueOptions);
        }
        if (isset($this->options['disable_inarray_validator'])) {
            $this->setDisableInArrayValidator(disableOption: \Jield\Stdlib\Value::bool($this->options['disable_inarray_validator']));
        }

        return $this;
    }

    /** @param mixed $value */
    public function setAttribute(string $key, $value): static
    {
        /** @psalm-suppress DocblockTypeContradiction */
        if ($key === 'options' && is_iterable(value: $value)) {
            trigger_error(
                message: 'Providing multi-checkbox value options via attributes is deprecated and will be removed in '
                . 'version 4.0 of this library',
                error_level: E_USER_DEPRECATED,
            );
            /** @var ValueOptionSpec $value */
            $this->setValueOptions(options: $value);
            return $this;
        }
        return parent::setAttribute(key: $key, value: \Jield\Stdlib\Value::plainScalar($value));
    }

    /**
     * Set the flag to allow for disabling the automatic addition of an InArray validator.
     *
     * @return static
     */
    public function setDisableInArrayValidator(bool $disableOption): static
    {
        $this->disableInArrayValidator = $disableOption;
        $this->validator = null;
        return $this;
    }

    /**
     * Get the disable in array validator flag.
     */
    public function disableInArrayValidator(): bool
    {
        return $this->disableInArrayValidator;
    }

    /**
     * Get validator
     */
    protected function getValidator(): ?ValidatorInterface
    {
        if (null === $this->validator && !$this->disableInArrayValidator()) {
            $inArrayValidator = new InArrayValidator(options: [
                'haystack' => $this->getValueOptionsValues(),
                'strict' => false,
            ]);
            $this->validator = new Each($inArrayValidator);
        }
        return $this->validator;
    }

    /**
     * Get only the values from the options attribute
     */
    protected function getValueOptionsValues(): array
    {
        $values = [];
        $options = $this->getValueOptions();
        foreach ($options as $key => $optionSpec) {
            $value = is_array(value: $optionSpec) ? $optionSpec['value'] : $key;
            $values[] = $value;
        }
        if ($this->useHiddenElement()) {
            $values[] = $this->getUncheckedValue();
        }
        return $values;
    }

    /** @inheritDoc */
    public function setValue(mixed $value): static
    {
        $this->value = $value;
        return $this;
    }
}
