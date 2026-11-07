<?php

declare(strict_types=1);

namespace Jield\Form\View\Helper;

use Jield\Form\Element\Hidden;
use Jield\Form\Element\Select as SelectElement;
use Jield\Form\ElementInterface;
use Jield\Form\Exception;
use Stringable;

use function array_key_exists;
use function array_map;
use function array_merge;
use function implode;
use function is_array;
use function is_scalar;
use function sprintf;
use function strval;

/**
 * @psalm-import-type ValueOptions from SelectElement
 * @psalm-import-type OptGroup from SelectElement
 */
// Preserve the existing public API and the state and operations that implement it.
// @mago-expect lint:cyclomatic-complexity(1)
// @mago-expect lint:kan-defect(1)
class FormSelect extends AbstractHelper
{
    /**
     * Attributes valid for select
     */
    protected array $validSelectAttributes = [
        'name' => true,
        'autocomplete' => true,
        'autofocus' => true,
        'disabled' => true,
        'form' => true,
        'multiple' => true,
        'required' => true,
        'size' => true,
    ];

    /**
     * Attributes valid for options
     */
    protected array $validOptionAttributes = [
        'disabled' => true,
        'selected' => true,
        'label' => true,
        'value' => true,
    ];

    /**
     * Attributes valid for option groups
     */
    protected array $validOptgroupAttributes = [
        'disabled' => true,
        'label' => true,
    ];

    /** @var array<string, bool> */
    protected array $translatableAttributes = [
        'label' => true,
    ];

    protected ?FormHidden $formHiddenHelper = null;

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
    public function __invoke(?ElementInterface $element = null): string|static
    {
        if (!$element) {
            return $this;
        }

        return $this->render(element: $element);
    }

    /**
     * Render a form <select> element from the provided $element
     *
     * @throws Exception\InvalidArgumentException
     * @throws Exception\DomainException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function render(ElementInterface $element): string
    {
        if (!$element instanceof SelectElement) {
            throw new Exception\InvalidArgumentException(message: sprintf(
                '%s requires that the element is of type Jield\Form\Element\Select',
                __METHOD__,
            ));
        }

        $name = $element->getName();
        if ($name === null || $name === '') {
            throw new Exception\DomainException(message: sprintf(
                '%s requires that the element has an assigned name; none discovered',
                __METHOD__,
            ));
        }

        $options = $element->getValueOptions();

        if (($emptyOption = $element->getEmptyOption()) !== null) {
            $options = ['' => $emptyOption] + $options;
        }

        $attributes = $element->getAttributes();
        $value = $this->validateMultiValue(value: $element->getValue(), attributes: $attributes);

        $attributes['name'] = $name;
        if (array_key_exists(key: 'multiple', array: $attributes) && $attributes['multiple']) {
            $attributes['name'] .= '[]';
        }
        $this->validTagAttributes = $this->validSelectAttributes;

        $rendered = sprintf(
            '<select %s>%s</select>',
            $this->createAttributesString(attributes: $attributes),
            $this->renderOptions(options: $options, selectedOptions: $value),
        );

        // Render hidden element
        if ($element->useHiddenElement()) {
            $rendered = $this->renderHiddenElement(element: $element) . $rendered;
        }

        return $rendered;
    }

    /**
     * Render an array of options
     *
     * Individual options should be of the form:
     *
     * <code>
     * array(
     *     'value'    => 'value',
     *     'label'    => 'label',
     *     'disabled' => $booleanFlag,
     *     'selected' => $booleanFlag,
     * )
     * </code>
     *
     * @param ValueOptions $options
     * @param  array $selectedOptions Option values that should be marked as selected
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function renderOptions(array $options, array $selectedOptions = []): string
    {
        $template = '<option %s>%s</option>';
        $optionStrings = [];
        $escapeHtml = $this->getEscapeHtmlHelper();

        $stringSelectedOptions = array_map(callback: strval(...), array: $selectedOptions);

        foreach ($options as $key => $optionSpec) {
            $value = '';
            $label = '';
            $selected = false;
            $disabled = false;

            if (is_scalar(value: $optionSpec) || $optionSpec instanceof Stringable) {
                $optionSpec = [
                    'label' => (string) $optionSpec,
                    'value' => $key,
                ];
            }

            /** @psalm-suppress RedundantConditionGivenDocblockType Retain defensive check that `options` is an array */
            if (($optionSpec['options'] ?? null) !== null) {
                $optionStrings[] = $this->renderOptgroup(optgroup: $optionSpec, selectedOptions: $selectedOptions);
                continue;
            }

            if (($optionSpec['value'] ?? null) !== null) {
                $value = $optionSpec['value'];
            }
            if (($optionSpec['label'] ?? null) !== null) {
                $label = $optionSpec['label'];
            }
            if (($optionSpec['selected'] ?? null) !== null) {
                $selected = $optionSpec['selected'];
            }
            if (($optionSpec['disabled'] ?? null) !== null) {
                $disabled = $optionSpec['disabled'];
            }

            if (in_array(needle: (string) $value, haystack: $stringSelectedOptions, strict: true)) {
                $selected = true;
            }

            $label = $this->translateLabel(label: $label);

            $attributes = [
                'value' => $value,
                'selected' => $selected,
                'disabled' => $disabled,
            ];

            /** @psalm-suppress RedundantConditionGivenDocblockType Retain defensive check that `attributes` is an array */
            if (($optionSpec['attributes'] ?? null) !== null) {
                $attributes = array_merge($attributes, $optionSpec['attributes']);
            }

            $this->validTagAttributes = $this->validOptionAttributes;
            $optionStrings[] = sprintf(
                $template,
                $this->createAttributesString(attributes: $attributes),
                \Jield\Stdlib\Value::scalar($escapeHtml(value: $label)),
            );
        }

        return implode(separator: "\n", array: $optionStrings);
    }

    /**
     * Render an optgroup
     *
     * See {@link renderOptions()} for the options specification. Basically,
     * an optgroup is simply an option that has an additional "options" key
     * with an array following the specification for renderOptions().
     *
     * @param OptGroup $optgroup
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function renderOptgroup(array $optgroup, array $selectedOptions = []): string
    {
        $template = '<optgroup%s>%s</optgroup>';

        $options = $optgroup['options'];
        unset($optgroup['options']);

        $this->validTagAttributes = $this->validOptgroupAttributes;
        $attributes = $this->createAttributesString(attributes: $optgroup);
        if ($attributes !== '') {
            $attributes = ' ' . $attributes;
        }

        return sprintf(
            $template,
            $attributes,
            $this->renderOptions(options: $options, selectedOptions: $selectedOptions),
        );
    }

    /**
     * Ensure that the value is set appropriately
     *
     * If the element's value attribute is an array, but there is no multiple
     * attribute, or that attribute does not evaluate to true, then we have
     * a domain issue -- you cannot have multiple options selected unless the
     * multiple attribute is present and enabled.
     *
     * @throws Exception\DomainException
     */
    protected function validateMultiValue(mixed $value, array $attributes): array
    {
        if (null === $value) {
            return [];
        }

        if (!is_array(value: $value)) {
            return [$value];
        }

        if (($attributes['multiple'] ?? null) === null || !$attributes['multiple']) {
            throw new Exception\DomainException(message: sprintf(
                '%s does not allow specifying multiple selected values when the element does not have a multiple '
                . 'attribute set to a boolean true',
                self::class,
            ));
        }

        return $value;
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function renderHiddenElement(SelectElement $element): string
    {
        $hiddenElement = new Hidden(name: $element->getName());
        $hiddenElement->setValue(value: $element->getUnselectedValue());

        return $this->getFormHiddenHelper()->__invoke(element: $hiddenElement);
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function getFormHiddenHelper(): FormHidden
    {
        if (null !== $this->formHiddenHelper) {
            return $this->formHiddenHelper;
        }

        if (null !== $this->view) {
            $this->formHiddenHelper = $this->getView()->plugin('formhidden', \Jield\Form\View\Helper\FormHidden::class);
        }

        $this->formHiddenHelper ??= new FormHidden();

        return $this->formHiddenHelper;
    }
}
