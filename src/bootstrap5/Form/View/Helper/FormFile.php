<?php

declare(strict_types=1);

namespace Jield\Bootstrap5\Form\View\Helper;

use Jield\Form\ElementInterface;
use Jield\Form\View\Helper;

use function count;

final class FormFile extends Helper\FormFile
{
    public function render(ElementInterface $element): string
    {
        $element->setAttribute(key: 'class', value: 'form-control');

        if (count(value: $element->getMessages()) > 0) {
            $element->setAttribute(key: 'class', value: 'form-control is-invalid');
        }

        $element->setAttribute(key: 'id', value: $element->getName());

        return parent::render(element: $element);
    }
}
