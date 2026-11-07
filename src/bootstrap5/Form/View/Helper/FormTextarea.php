<?php

declare(strict_types=1);

namespace Jield\Bootstrap5\Form\View\Helper;

use Jield\Form\ElementInterface;
use Jield\Form\View\Helper;
use function count;

/**
 * Class FormTextarea
 * @package Jield\Bootstrap5\Form\View\Helper
 */
final class FormTextarea extends Helper\FormTextarea
{
    public function render(ElementInterface $element): string
    {
        if (null === $element->getAttribute(key: 'class')) {
            $element->setAttribute(key: 'class', value: 'form-control');
        }

        if (count(value: $element->getMessages()) > 0) {
            $element->setAttribute(key: 'class', value: (string) $element->getAttribute(key: 'class') . ' is-invalid');
        }

        if (null === $element->getAttribute(key: 'id')) {
            $element->setAttribute(key: 'id', value: $element->getName());
        }

        return parent::render(element: $element);
    }
}
