<?php

namespace Jield\Bootstrap5\Form\View\Helper;

use Jield\Form\ElementInterface;
use Jield\Form\View\Helper;

use function count;

/**
 * Class FormSelect
 * @package Jield\Bootstrap5\Form\View\Helper
 */
final class FormSelect extends Helper\FormSelect
{
    public function render(ElementInterface $element): string
    {
        if (null === $element->getAttribute(key: 'class')) {
            $element->setAttribute(key: 'class', value: 'form-select');
        }

        if (count(value: $element->getMessages()) > 0) {
            $element->setAttribute(key: 'class', value: $element->getAttribute(key: 'class') . ' is-invalid');
        }

        if (null === $element->getAttribute(key: 'id')) {
            $element->setAttribute(key: 'id', value: $element->getName());
        }

        return parent::render(element: $element);
    }
}
