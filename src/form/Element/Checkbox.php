<?php

declare(strict_types=1);

namespace Jield\Form\Element;

use Jield\Form\Element;
use Jield\Stdlib\Value;
use Laminas\InputFilter\InputProviderInterface;
use Laminas\Validator\InArray as InArrayValidator;
use Laminas\Validator\ValidatorInterface;

class Checkbox extends Element implements InputProviderInterface
{
    /** @var array<string, scalar|null>  */
    protected array $attributes = [
        'type' => 'checkbox',
    ];

    /** @var null|ValidatorInterface */
    protected ?ValidatorInterface $validator = null;

    /** @var bool */
    protected bool $useHiddenElement = true;

    /** @var null|string */
    protected ?string $uncheckedValue = '0';

    /** @var string */
    protected string $checkedValue = '1';

    /**
     * Accepted options for MultiCheckbox:
     * - use_hidden_element: do we render hidden element?
     * - unchecked_value: value for checkbox when unchecked
     * - checked_value: value for checkbox when checked
     *
     * @inheritDoc
     */
    public function setOptions(iterable $options): static
    {
        parent::setOptions(options: $options);

        if (isset($this->options['use_hidden_element'])) {
            $this->setUseHiddenElement(useHiddenElement: Value::bool(
                $this->options['use_hidden_element'],
            ));
        }

        if (isset($this->options['unchecked_value'])) {
            $this->setUncheckedValue(uncheckedValue: Value::nullableString(
                $this->options['unchecked_value'],
            ));
        }

        if (isset($this->options['checked_value'])) {
            $this->setCheckedValue(checkedValue: Value::string($this->options['checked_value']));
        }

        return $this;
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
     * Set the value to use when checkbox is unchecked
     *
     * @return static
     */
    public function setUncheckedValue(?string $uncheckedValue): static
    {
        $this->uncheckedValue = $uncheckedValue;
        return $this;
    }

    /**
     * Get the value to use when checkbox is unchecked
     */
    public function getUncheckedValue(): ?string
    {
        return $this->uncheckedValue;
    }

    /**
     * Set the value to use when checkbox is checked
     *
     * @return static
     */
    public function setCheckedValue(string $checkedValue): static
    {
        $this->checkedValue = $checkedValue;
        return $this;
    }

    /**
     * Get the value to use when checkbox is checked
     */
    public function getCheckedValue(): string
    {
        return $this->checkedValue;
    }

    /**
     * Get validator
     */
    protected function getValidator(): ?ValidatorInterface
    {
        $this->validator ??= new InArrayValidator(options: [
            'haystack' => [$this->checkedValue, $this->uncheckedValue],
            'strict' => false,
        ]);
        return $this->validator;
    }

    /**
     * Provide default input rules for this element
     *
     * Attaches the captcha as a validator.
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

        if ($validator = $this->getValidator()) {
            $spec['validators'] = [
                $validator,
            ];
        }

        return $spec;
    }

    /**
     * Checks if this checkbox is checked.
     */
    public function isChecked(): bool
    {
        return $this->value === $this->getCheckedValue();
    }

    /**
     * Checks or unchecks the checkbox.
     *
     * @param bool $value The flag to set.
     * @return static
     */
    public function setChecked(bool $value): static
    {
        $this->value = $value ? $this->getCheckedValue() : $this->getUncheckedValue();
        return $this;
    }

    /**
     * Checks or unchecks the checkbox.
     *
     * @param mixed $value A boolean flag or string that is checked against the "checked value".
     * @inheritDoc
     */
    public function setValue(mixed $value): static
    {
        // Cast to strings because POST data comes in string form
        $checked = (string) $value === $this->getCheckedValue();
        $this->value = $checked ? $this->getCheckedValue() : $this->getUncheckedValue();
        return $this;
    }
}
