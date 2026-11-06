<?php

declare(strict_types=1);

namespace Jield\Bootstrap5\Form\View\Helper;

use Jield\Form\ElementInterface;
use Jield\Form\View\Helper;

use function count;
use function sort;

final class FormMultiSlider extends Helper\FormText
{
    public function render(ElementInterface $element): string
    {
        $id = md5(string: \Jield\Stdlib\Value::string($element->getName()));

        if (count(value: $element->getMessages()) > 0) {
            $element->setAttribute(key: 'class', value: (string) ($element->getAttribute(key: 'class')) . ' is-invalid');
        }

        $element->setAttribute(key: 'class', value: (string) ($element->getAttribute(key: 'class')) . ' form-multi-slider');

        $element->setAttribute(key: 'id', value: $id);

        // Grab the raw values (keys).
        $rawValueOptions = array_keys(array: \Jield\Stdlib\Value::array(\Jield\Stdlib\Value::instance($element, \Jield\Form\Element\MultiCheckbox::class)->getValueOptions()));

        // Sort values from low to high.
        sort($rawValueOptions);

        $minValue = reset(array: $rawValueOptions);
        $maxValue = end(array: $rawValueOptions);

        /** @var mixed $value */
        $value = $element->getValue(); // The SearchFormResult helper transforms the result into an array.

        $element->setAttribute(key: 'data-provide', value: 'slider');
        $element->setAttribute(key: 'data-slider-min', value: $minValue);
        $element->setAttribute(key: 'data-slider-max', value: $maxValue);
        $element->setAttribute(key: 'data-slider-ticks', value: '[' . (string) ($minValue) . ',' . (string) ($maxValue) . ']');
        $element->setAttribute(key: 'data-slider-ticks-labels', value: '[' . (string) ($minValue) . ',' . (string) ($maxValue) . ']');
        $element->setAttribute(key: 'data-slider-value', value: '[' . \Jield\Stdlib\Value::stringify($value) . ',' . \Jield\Stdlib\Value::stringify($value) . ']');

        return parent::render(element: $element);
    }
}
