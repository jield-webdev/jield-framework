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

class FormMultiCheckbox extends FormInput
{
    public const string LABEL_APPEND = 'append';
    public const string LABEL_PREPEND = 'prepend';

    /**
     * The attributes applied to option label
     *
     * @var null|array
     */
    protected ?array $labelAttributes = null;

    /**
     * Where will be label rendered?
     *
     * @var string
     */
    protected string $labelPosition = self::LABEL_APPEND;

    /**
     * Separator for checkbox elements
     *
     * @var string
     */
    protected string $separator = '';

    /**
     * Prefixing the element with a hidden element for the unset value?
     *
     * @var bool
     */
    protected bool $useHiddenElement = false;

    /**
     * The unchecked value used when "UseHiddenElement" is turned on
     *
     * @var null|string
     */
    protected ?string $uncheckedValue = null;

    /**
     * Form input helper instance
     *
     * @var null|FormInput
     */
    protected ?FormInput $inputHelper = null;

    /**
     * Form label helper instance
     *
     * @var null|FormLabel
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
     * @return string|FormMultiCheckbox
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
     */
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

        if (empty($globalLabelAttributes)) {
            $globalLabelAttributes = $this->labelAttributes;
        }

        $combinedMarkup = [];
        $count = 0;

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
                isset($inputAttributes['selected'])
                && $inputAttributes['type'] !== 'radio'
                && Value::truthy($inputAttributes['selected']);
            $disabled = isset($inputAttributes['disabled']) && Value::truthy($inputAttributes['disabled']);

            if (is_scalar(value: $optionSpec)) {
                $optionSpec = [
                    'label' => $optionSpec,
                    'value' => $key,
                ];
            }

            if (isset($optionSpec['value'])) {
                $value = $optionSpec['value'];
            }
            if (isset($optionSpec['label'])) {
                $label = $optionSpec['label'];
            }
            if (isset($optionSpec['selected'])) {
                $selected = $optionSpec['selected'];
            }
            if (isset($optionSpec['disabled'])) {
                $disabled = $optionSpec['disabled'];
            }
            if (isset($optionSpec['label_attributes'])) {
                $labelAttributes = isset($labelAttributes)
                    ? array_merge($labelAttributes, Assert::isArray($optionSpec['label_attributes']))
                    : $optionSpec['label_attributes'];
            }
            if (isset($optionSpec['attributes'])) {
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
     *
     * @return static
     */
    public function setLabelAttributes(?array $attributes): static
    {
        $this->labelAttributes = $attributes;
        return $this;
    }

    /**
     * Returns the attributes applied to each option label.
     *
     * @return array|null
     */
    public function getLabelAttributes(): ?array
    {
        return $this->labelAttributes;
    }

    /**
     * Set value for labelPosition
     *
     * @throws Exception\InvalidArgumentException
     * @return static
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
     *
     * @return static
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
     *
     * @return static
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
     *
     * @return static
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
     */
    protected function getInputHelper(): FormInput
    {
        if (null !== $this->inputHelper) {
            return $this->inputHelper;
        }

        if (null !== $this->view && method_exists(object_or_class: $this->view, method: 'plugin')) {
            $this->inputHelper = $this->getView()->plugin('form_input', \Jield\Form\View\Helper\FormInput::class);
        }

        $this->inputHelper ??= new FormInput();

        return $this->inputHelper;
    }

    /**
     * Retrieve the FormLabel helper
     */
    protected function getLabelHelper(): FormLabel
    {
        if ($this->labelHelper) {
            return $this->labelHelper;
        }

        if (method_exists(object_or_class: $this->view, method: 'plugin')) {
            $this->labelHelper = $this->getView()->plugin('form_label', \Jield\Form\View\Helper\FormLabel::class);
        }

        if (!$this->labelHelper instanceof FormLabel) {
            $this->labelHelper = new FormLabel();
        }

        return $this->labelHelper;
    }
}
