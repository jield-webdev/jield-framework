<?php

namespace Jield\Bootstrap5\Form\View\Helper;

use Jield\Form\Element\MultiCheckbox;
use Jield\Form\ElementInterface;
use Jield\Form\LabelAwareInterface;
use Jield\Form\View\Helper;

use function array_key_exists;
use function array_merge;
use function count;
use function implode;
use function in_array;
use function is_scalar;
use function md5;
use function method_exists;

/**
 * Class FormMultiCheckbox
 * @package Jield\Bootstrap5\Form\View\Helper
 */
class FormMultiCheckbox extends Helper\FormMultiCheckbox
{
    private string $template = '<div class="form-check %s">%s%s%s%s</div>';

    public function render(ElementInterface|MultiCheckbox $element): string
    {
        $name = static::getName(element: $element);

        $options = $element->getValueOptions();

        $attributes         = $element->getAttributes();
        $attributes['name'] = $name;
        $attributes['type'] = $this->getInputType();
        $selectedOptions    = (array)$element->getValue();

        $rendered = $this->renderOptions(element: $element, options: $options, selectedOptions: $selectedOptions, attributes: $attributes);

        // Render hidden element
        $useHiddenElement = method_exists(object_or_class: $element, method: 'useHiddenElement') && $element->useHiddenElement()
            ? $element->useHiddenElement()
            : $this->useHiddenElement;

        if ($useHiddenElement) {
            $rendered = $this->renderHiddenElement(element: $element) . $rendered;
        }

        return $rendered;
    }

    protected function renderOptions(
        ElementInterface $element,
        array $options,
        array $selectedOptions,
        array $attributes
    ): string {
        $escapeHtmlHelper      = $this->getEscapeHtmlHelper();
        $labelHelper           = $this->getLabelHelper();
        $labelClose            = $labelHelper->closeTag();
        $labelPosition         = $this->getLabelPosition();
        $globalLabelAttributes = [];
        $closingBracket        = $this->getInlineClosingBracket();

        if ($element instanceof LabelAwareInterface) {
            $globalLabelAttributes = $element->getLabelAttributes();
        }

        if (empty($globalLabelAttributes)) {
            $globalLabelAttributes = $this->labelAttributes;
        }

        $combinedMarkup = [];
        $count          = 0;

        foreach ($options as $key => $optionSpec) {
            $count++;
            if ($count > 1 && array_key_exists(key: 'id', array: $attributes)) {
                unset($attributes['id']);
            }

            $value           = '';
            $label           = '';
            $inputAttributes = $attributes;
            $labelAttributes = $globalLabelAttributes;
            $selected        = (isset($inputAttributes['selected'])
                && $inputAttributes['type'] !== 'radio'
                && $inputAttributes['selected']);
            $disabled        = (isset($inputAttributes['disabled']) && $inputAttributes['disabled']);

            if (is_scalar(value: $optionSpec)) {
                $optionSpec = [
                    'label' => $optionSpec,
                    'value' => $key
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
                $labelAttributes = array_merge($labelAttributes, $optionSpec['label_attributes']);
            }
            if (isset($optionSpec['attributes'])) {
                $inputAttributes = array_merge($inputAttributes, $optionSpec['attributes']);
            }

            if (in_array(needle: $value, haystack: $selectedOptions)) {
                $selected = true;
            }

            $elementId                = md5(string: $element->getName() . $label . $value);
            $inputAttributes['class'] = 'form-check-input';

            if (count(value: $element->getMessages()) > 0) {
                $inputAttributes['class'] = 'form-check-input is-invalid';
            }

            $inputAttributes['id'] = $elementId;

            $inputAttributes['value']    = $value;
            $inputAttributes['checked']  = $selected;
            $inputAttributes['disabled'] = $disabled;

            $input = sprintf(
                '<input %s%s',
                $this->createAttributesString(attributes: $inputAttributes),
                $closingBracket
            );

            if (null !== ($translator = $this->getTranslator())) {
                $label = $translator->translate(
                    $label,
                    $this->getTranslatorTextDomain()
                );
            }

            if (!$element instanceof LabelAwareInterface || !$element->getLabelOption(key: 'disable_html_escape')) {
                $label = $escapeHtmlHelper(value: $label);
            }

            $labelAttributes['for']   = $elementId;
            $labelAttributes['class'] = 'form-check-label';

            $labelOpen = $labelHelper->openTag(attributesOrElement: $labelAttributes);

            $inlineClass = '';

            switch ($labelPosition) {
                case self::LABEL_PREPEND:
                    $markup = sprintf($this->template, $inlineClass, $label, $labelOpen, $input, $labelClose);
                    break;
                case self::LABEL_APPEND:
                default:
                    $markup = sprintf($this->template, $inlineClass, $input, $labelOpen, $label, $labelClose);
                    break;
            }

            $combinedMarkup[] = $markup;
        }

        return implode(separator: $this->getSeparator(), array: $combinedMarkup);
    }

    public function setTemplate(string $template): self
    {
        $this->template = $template;

        return $this;
    }
}
