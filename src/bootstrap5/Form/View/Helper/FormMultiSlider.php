<?php

declare(strict_types=1);

namespace Jield\Bootstrap5\Form\View\Helper;

use Jield\Form\Element\MultiCheckbox;
use Jield\Form\ElementInterface;
use Jield\Form\View\Helper;
use Jield\Stdlib\Value;
use Webmozart\Assert\Assert;

use function count;
use function sort;

final class FormMultiSlider extends Helper\FormText
{
    /**
     * @throws \Jield\Form\Exception\ExceptionInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function render(ElementInterface $element): string
    {
        $id = md5(string: Assert::string($element->getName()));

        if (count(value: $element->getMessages()) > 0) {
            $element->setAttribute(key: 'class', value: (string) $element->getAttribute(key: 'class') . ' is-invalid');
        }

        $element->setAttribute(
            key: 'class',
            value: (string) $element->getAttribute(key: 'class') . ' form-multi-slider',
        );

        $element->setAttribute(key: 'id', value: $id);

        // Grab the raw values (keys).
        $rawValueOptions = array_keys(array: Assert::isInstanceOf($element, MultiCheckbox::class)->getValueOptions());

        // Sort values from low to high.
        sort($rawValueOptions);

        $minValue = reset(array: $rawValueOptions);
        $maxValue = end(array: $rawValueOptions);

        /** @mago-var mixed $value */
        $value = $element->getValue(); // The SearchFormResult helper transforms the result into an array.

        $element->setAttribute(key: 'data-provide', value: 'slider');
        $element->setAttribute(key: 'data-slider-min', value: $minValue);
        $element->setAttribute(key: 'data-slider-max', value: $maxValue);
        $element->setAttribute(
            key: 'data-slider-ticks',
            value: '[' . (string) $minValue . ',' . (string) $maxValue . ']',
        );
        $element->setAttribute(
            key: 'data-slider-ticks-labels',
            value: '[' . (string) $minValue . ',' . (string) $maxValue . ']',
        );
        $element->setAttribute(
            key: 'data-slider-value',
            value: '[' . Value::stringify($value) . ',' . Value::stringify($value) . ']',
        );

        return parent::render(element: $element);
    }
}
