<?php

declare(strict_types=1);

namespace Jield\Form\Element;

use Jield\Form\Element;
use Jield\Form\Exception\InvalidArgumentException;
use Jield\Stdlib\Value;
use Jield\Validator\Each;
use Laminas\InputFilter\InputProviderInterface;
use Laminas\Validator\InArray as InArrayValidator;
use Laminas\Validator\ValidatorInterface;
use Stringable;

use function array_key_exists;
use function is_array;
use function is_iterable;
use function trigger_error;

use const E_USER_DEPRECATED;

/**
 * @psalm-type ValueOption = array{
 *     value?: string|int|Stringable,
 *     label?: string,
 *     selected?: bool,
 *     disabled?: bool,
 *     attributes?: array<array-key, mixed>,
 * }|scalar|Stringable
 * @psalm-type EmptyOption = string|array{value?: string|int|Stringable, label?: string, selected?: bool, disabled?: bool, attributes?: array<array-key, mixed>}
 * @psalm-type BasicValueOptions = array<array-key, ValueOption>
 * @psalm-type OptGroup = array{
 *     options: BasicValueOptions,
 *     disabled?: bool,
 *     label?: string,
 *     value?: string|int|Stringable,
 *     selected?: bool,
 *     attributes?: array<array-key, mixed>,
 * }
 * @psalm-type ValueOptions = array<array-key, OptGroup|ValueOption>
 */
class Select extends Element implements InputProviderInterface
{
    /** @var array<string, scalar|null>  */
    protected array $attributes = [
        'type' => 'select',
    ];

    /** @var null|ValidatorInterface */
    protected ?ValidatorInterface $validator = null;

    /** @var bool */
    protected bool $disableInArrayValidator = false;

    /**
     * Create an empty option (option with label but no value). If set to null, no option is created
     *
     * @var EmptyOption|null
     */
    protected string|array|null $emptyOption = null;

    /** @var ValueOptions */
    protected array $valueOptions = [];

    /** @var bool */
    protected bool $useHiddenElement = false;

    /** @var string */
    protected string $unselectedValue = '';

    /** @return ValueOptions */
    public function getValueOptions(): array
    {
        return $this->valueOptions;
    }

    /**
     * @param ValueOptions $options
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
     * - empty_option: should an empty option be prepended to the options ?
     *
     * @return static
     * @throws InvalidArgumentException
     */
    public function setOptions(iterable $options): static
    {
        parent::setOptions(options: $options);

        if (isset($this->options['value_options'])) {
            /** @var ValueOptions $valueOptions */
            $valueOptions = $this->options['value_options'];
            $this->setValueOptions(options: $valueOptions);
        }
        // Alias for 'value_options'
        if (isset($this->options['options'])) {
            /** @var ValueOptions $valueOptions */
            $valueOptions = $this->options['options'];
            $this->setValueOptions(options: $valueOptions);
        }

        if (isset($this->options['empty_option'])) {
            /** @var EmptyOption $emptyOption */
            $emptyOption = $this->options['empty_option'];
            $this->setEmptyOption(emptyOption: $emptyOption);
        }

        if (isset($this->options['disable_inarray_validator'])) {
            $this->setDisableInArrayValidator(disableOption: Value::bool(
                $this->options['disable_inarray_validator'],
            ));
        }

        if (isset($this->options['use_hidden_element'])) {
            $this->setUseHiddenElement(useHiddenElement: Value::bool(
                $this->options['use_hidden_element'],
            ));
        }

        if (isset($this->options['unselected_value'])) {
            $this->setUnselectedValue(unselectedValue: Value::string($this->options['unselected_value']));
        }

        return $this;
    }

    /** @param mixed $value */
    public function setAttribute(string $key, $value): static
    {
        /** @psalm-suppress DocblockTypeContradiction */
        if ($key === 'options' && is_iterable(value: $value)) {
            trigger_error(
                message: 'Providing multi-select value options via attributes is deprecated and will be removed in '
                . 'version 4.0 of this library',
                error_level: E_USER_DEPRECATED,
            );
            /** @var ValueOptions $value */
            $this->setValueOptions(options: $value);
            return $this;
        }
        return parent::setAttribute(key: $key, value: Value::plainScalar($value));
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
     * Set the string for an empty option (can be empty string). If set to null, no option will be added
     *
     * @param EmptyOption|null $emptyOption
     * @return static
     */
    public function setEmptyOption(array|string|null $emptyOption): static
    {
        $this->emptyOption = $emptyOption;
        return $this;
    }

    /**
     * Return the string for the empty option (null if none)
     *
     * @return EmptyOption|null
     */
    public function getEmptyOption(): array|string|null
    {
        return $this->emptyOption;
    }

    /**
     * Get validator
     */
    protected function getValidator(): ?ValidatorInterface
    {
        if (null === $this->validator && !$this->disableInArrayValidator()) {
            $validator = new InArrayValidator(options: [
                'haystack' => $this->getValueOptionsValues(),
                'strict' => false,
            ]);

            if ($this->isMultiple()) {
                $validator = new Each($validator);
            }

            $this->validator = $validator;
        }
        return $this->validator;
    }

    /**
     * Do we render hidden element?
     *
     * @return static
     */
    public function setUseHiddenElement(bool $useHiddenElement): static
    {
        $this->useHiddenElement = $useHiddenElement;
        return $this;
    }

    /**
     * Do we render hidden element?
     */
    public function useHiddenElement(): bool
    {
        return $this->useHiddenElement;
    }

    /**
     * Set the value if the select is not selected
     *
     * @return static
     */
    public function setUnselectedValue(string $unselectedValue): static
    {
        $this->unselectedValue = $unselectedValue;
        return $this;
    }

    /**
     * Get the value when the select is not selected
     */
    public function getUnselectedValue(): string
    {
        return $this->unselectedValue;
    }

    /**
     * Provide default input rules for this element
     *
     * @inheritDoc
     */
    public function getInputSpecification(): array
    {
        $spec = [
            'required' => true,
        ];

        $name = $this->getName();
        if ($name !== null) {
            $spec['name'] = $name;
        }

        if ($this->useHiddenElement() && $this->isMultiple()) {
            $unselectedValue = $this->getUnselectedValue();

            $spec['allow_empty'] = true;
            $spec['continue_if_empty'] = true;
            $spec['filters'] = [
                [
                    'name' => 'Callback',
                    'options' => [
                        'callback' => static function ($value) use ($unselectedValue) {
                            if ($value === $unselectedValue) {
                                $value = [];
                            }
                            return $value;
                        },
                    ],
                ],
            ];
        }

        if ($validator = $this->getValidator()) {
            $spec['validators'] = [
                $validator,
            ];
        }

        return $spec;
    }

    /**
     * Get only the values from the options attribute
     */
    protected function getValueOptionsValues(): array
    {
        $values = [];
        $options = $this->getValueOptions();
        foreach ($options as $key => $optionSpec) {
            if (is_array(value: $optionSpec) && array_key_exists(key: 'options', array: $optionSpec)) {
                foreach ($optionSpec['options'] as $nestedKey => $nestedOptionSpec) {
                    $values[] = $this->getOptionValue(key: $nestedKey, optionSpec: $nestedOptionSpec);
                }
                continue;
            }

            $values[] = $this->getOptionValue(key: $key, optionSpec: $optionSpec);
        }
        return $values;
    }

    /**
     * @return mixed
     * @param (int|string) $key
     * @psalm-param array-key $key
     */
    protected function getOptionValue(mixed $key, mixed $optionSpec): mixed
    {
        return is_array(value: $optionSpec) ? $optionSpec['value'] : $key;
    }

    /**
     * Element has the multiple attribute
     */
    public function isMultiple(): bool
    {
        return (
            isset($this->attributes['multiple'])
            && ($this->attributes['multiple'] === true || $this->attributes['multiple'] === 'multiple')
        );
    }
}
