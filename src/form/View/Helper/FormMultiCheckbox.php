<?php

declare(strict_types=1);

namespace Jield\Form\View\Helper;

use Jield\Form\Element\MultiCheckbox as MultiCheckboxElement;
use Jield\Form\ElementInterface;
use Jield\Form\Exception;
use Jield\Stdlib\Value;
use Webmozart\Assert\Assert;

use function array_key_exists;
use function array_merge;
use function implode;
use function in_array;
use function is_scalar;
use function method_exists;
use function sprintf;
use function strtolower;

// Preserve the existing public API and the state and operations that implement it.
// @mago-expect lint:cyclomatic-complexity(1)
// @mago-expect lint:kan-defect(1)
class FormMultiCheckbox extends FormInput
{
    public const string LABEL_APPEND = 'append';
    public const string LABEL_PREPEND = 'prepend';

    /**
     * The attributes applied to option label
     */
    protected ?array $labelAttributes = null;

    /**
     * Where will be label rendered?
     */
    protected string $labelPosition = self::LABEL_APPEND;

    /**
     * Separator for checkbox elements
     */
    protected string $separator = '';

    /**
     * Prefixing the element with a hidden element for the unset value?
     */
    protected bool $useHiddenElement = false;

    /**
     * The unchecked value used when "UseHiddenElement" is turned on
     */
    protected ?string $uncheckedValue = null;

    /**
     * Form input helper instance
     */
    protected ?FormInput $inputHelper = null;

    /**
     * Form label helper instance
     */
    protected ?FormLabel $labelHelper = null;

    /**
     * Invoke helper as functor
     *
     * Proxies to {@link render()}.
     *
     * @template T as null|ElementInterface
     * @psalm-param T $element
     * @psalm-return (T is null ? static : string)
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __invoke(?ElementInterface $element = null, ?string $labelPosition = null): string|static
    {
        if (!$element) {
            return $this;
        }

        if ($labelPosition !== null) {
            $this->setLabelPosition(labelPosition: $labelPosition);
        }

        return $this->render(element: $element);
    }

    /**
     * Render a form <input> element from the provided $element
     *
     * @throws Exception\InvalidArgumentException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function render(ElementInterface $element): string
    {
        if (!$element instanceof MultiCheckboxElement) {
            throw new Exception\InvalidArgumentException(message: sprintf(
                '%s requires that the element is of type Jield\Form\Element\MultiCheckbox',
                __METHOD__,
            ));
        }

        $name = static::getName(element: $element);

        $options = $element->getValueOptions();

        $attributes = $element->getAttributes();
        $attributes['name'] = $name;
        $attributes['type'] = $this->getInputType();
        $selectedOptions = Value::toArray($element->getValue());

        $rendered = $this->renderOptions(
            element: $element,
            options: $options,
            selectedOptions: $selectedOptions,
            attributes: $attributes,
        );

        // Render hidden element
        if ($element->useHiddenElement()) {
            $rendered = $this->renderHiddenElement(element: $element) . $rendered;
        }

        return $rendered;
    }

    /**
     * Render options
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    // Keep the coordinated parsing or rendering steps of this compatibility method together.
    // @mago-expect lint:halstead(1)
    protected function renderOptions(
        MultiCheckboxElement $element,
        array $options,
        array $selectedOptions,
        array $attributes,
    ): string {
        $labelHelper = $this->getLabelHelper();
        $labelClose = $labelHelper->closeTag();
        $labelPosition = $this->getLabelPosition();
        $globalLabelAttributes = [];
        $closingBracket = $this->getInlineClosingBracket();

        $globalLabelAttributes = $element->getLabelAttributes();

        if ($globalLabelAttributes === []) {
            $globalLabelAttributes = $this->labelAttributes;
        }

        $combinedMarkup = [];
        $count = 0;

        /** @mago-var mixed $optionSpec */
        foreach ($options as $key => $optionSpec) {
            $count++;
            if ($count > 1 && array_key_exists(key: 'id', array: $attributes)) {
                unset($attributes['id']);
            }

            $value = '';
            $label = '';
            $inputAttributes = $attributes;
            $labelAttributes = $globalLabelAttributes;
            $selected =
                ($inputAttributes['selected'] ?? null) !== null
                && $inputAttributes['type'] !== 'radio'
                && Value::truthy($inputAttributes['selected']);
            $disabled = ($inputAttributes['disabled'] ?? null) !== null && Value::truthy($inputAttributes['disabled']);

            if (is_scalar(value: $optionSpec)) {
                $optionSpec = [
                    'label' => $optionSpec,
                    'value' => $key,
                ];
            }

            if (($optionSpec['value'] ?? null) !== null) {
                /** @mago-var mixed $value */
                $value = $optionSpec['value'];
            }
            if (($optionSpec['label'] ?? null) !== null) {
                /** @mago-var mixed $label */
                $label = $optionSpec['label'];
            }
            if (($optionSpec['selected'] ?? null) !== null) {
                /** @mago-var mixed $selected */
                $selected = $optionSpec['selected'];
            }
            if (($optionSpec['disabled'] ?? null) !== null) {
                /** @mago-var mixed $disabled */
                $disabled = $optionSpec['disabled'];
            }
            if (($optionSpec['label_attributes'] ?? null) !== null) {
                /** @mago-var mixed $labelAttributes */
                $labelAttributes = ($labelAttributes ?? null) !== null
                    ? array_merge($labelAttributes, Assert::isArray($optionSpec['label_attributes']))
                    : $optionSpec['label_attributes'];
            }
            if (($optionSpec['attributes'] ?? null) !== null) {
                $inputAttributes = array_merge($inputAttributes, Assert::isArray($optionSpec['attributes']));
            }

            if (in_array(needle: $value, haystack: $selectedOptions, strict: true)) {
                $selected = true;
            }

            $inputAttributes['value'] = $value;
            $inputAttributes['checked'] = $selected;
            $inputAttributes['disabled'] = $disabled;

            $input = sprintf(
                '<input %s%s',
                $this->createAttributesString(attributes: $inputAttributes),
                $closingBracket,
            );

            $label = $this->translateLabel(label: Assert::string($label));
            $label = $this->escapeLabel(element: $element, label: $label);

            $labelOpen = $labelHelper->openTag(attributesOrElement: Assert::isArray($labelAttributes));
            $template = $labelOpen . '%s%s' . $labelClose;
            $markup = match ($labelPosition) {
                self::LABEL_PREPEND => sprintf($template, $label, $input),
                default => sprintf($template, $input, $label),
            };

            $combinedMarkup[] = $markup;
        }

        return implode(separator: $this->getSeparator(), array: $combinedMarkup);
    }

    /**
     * Render a hidden element for empty/unchecked value
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function renderHiddenElement(MultiCheckboxElement $element): string
    {
        $closingBracket = $this->getInlineClosingBracket();

        $uncheckedValue = $element->getUncheckedValue() ?? $this->uncheckedValue;

        $hiddenAttributes = [
            'name' => $element->getName(),
            'value' => $uncheckedValue,
        ];

        return sprintf(
            '<input type="hidden" %s%s',
            $this->createAttributesString(attributes: $hiddenAttributes),
            $closingBracket,
        );
    }

    /**
     * Sets the attributes applied to option label.
     */
    public function setLabelAttributes(?array $attributes): static
    {
        $this->labelAttributes = $attributes;
        return $this;
    }

    /**
     * Returns the attributes applied to each option label.
     */
    public function getLabelAttributes(): ?array
    {
        return $this->labelAttributes;
    }

    /**
     * Set value for labelPosition
     *
     * @throws Exception\InvalidArgumentException
     */
    public function setLabelPosition(string $labelPosition): static
    {
        $labelPosition = strtolower(string: $labelPosition);
        if (!in_array(needle: $labelPosition, haystack: [self::LABEL_APPEND, self::LABEL_PREPEND], strict: true)) {
            throw new Exception\InvalidArgumentException(message: sprintf(
                '%s expects either %s::LABEL_APPEND or %s::LABEL_PREPEND; received "%s"',
                __METHOD__,
                self::class,
                self::class,
                $labelPosition,
            ));
        }
        $this->labelPosition = $labelPosition;

        return $this;
    }

    /**
     * Get position of label
     */
    public function getLabelPosition(): string
    {
        return $this->labelPosition;
    }

    /**
     * Set separator string for checkbox elements
     */
    public function setSeparator(string $separator): static
    {
        $this->separator = $separator;
        return $this;
    }

    /**
     * Get separator for checkbox elements
     */
    public function getSeparator(): string
    {
        return $this->separator;
    }

    /**
     * Sets the option for prefixing the element with a hidden element
     * for the unset value.
     */
    public function setUseHiddenElement(bool $useHiddenElement): static
    {
        $this->useHiddenElement = $useHiddenElement;
        return $this;
    }

    /**
     * Returns the option for prefixing the element with a hidden element
     * for the unset value.
     */
    public function getUseHiddenElement(): bool
    {
        return $this->useHiddenElement;
    }

    /**
     * Sets the unchecked value used when "UseHiddenElement" is turned on.
     */
    public function setUncheckedValue(?string $value): static
    {
        $this->uncheckedValue = $value;
        return $this;
    }

    /**
     * Returns the unchecked value used when "UseHiddenElement" is turned on.
     */
    public function getUncheckedValue(): ?string
    {
        return $this->uncheckedValue;
    }

    /**
     * Return input type
     */
    protected function getInputType(): string
    {
        return 'checkbox';
    }

    /**
     * Get element name
     *
     * @throws Exception\DomainException
     */
    protected static function getName(ElementInterface $element): string
    {
        $name = $element->getName();
        if ($name === null || $name === '') {
            throw new Exception\DomainException(message: sprintf(
                '%s requires that the element has an assigned name; none discovered',
                __METHOD__,
            ));
        }
        return $name . '[]';
    }

    /**
     * Retrieve the FormInput helper
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function getInputHelper(): FormInput
    {
        if (null !== $this->inputHelper) {
            return $this->inputHelper;
        }

        if (null !== $this->view) {
            $this->inputHelper = $this->getView()->plugin('form_input', \Jield\Form\View\Helper\FormInput::class);
        }

        $this->inputHelper ??= new FormInput();

        return $this->inputHelper;
    }

    /**
     * Retrieve the FormLabel helper
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function getLabelHelper(): FormLabel
    {
        if ($this->labelHelper) {
            return $this->labelHelper;
        }

        if (method_exists(object_or_class: Value::objectOrString($this->view), method: 'plugin')) {
            $this->labelHelper = $this->getView()->plugin('form_label', \Jield\Form\View\Helper\FormLabel::class);
        }

        if (!$this->labelHelper instanceof FormLabel) {
            $this->labelHelper = new FormLabel();
        }

        return $this->labelHelper;
    }
}
