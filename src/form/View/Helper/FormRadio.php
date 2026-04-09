<?php

declare(strict_types=1);

namespace Jield\Form\View\Helper;

use Jield\Form\ElementInterface;

/** @final */
class FormRadio extends FormMultiCheckbox
{
    /**
     * Return input type
     */
    protected function getInputType(): string
    {
        return 'radio';
    }

    /**
     * Get element name
     */
    protected static function getName(ElementInterface $element): string
    {
        return (string) $element->getName();
    }
}
