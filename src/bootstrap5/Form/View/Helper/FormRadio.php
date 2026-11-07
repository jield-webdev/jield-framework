<?php

declare(strict_types=1);

namespace Jield\Bootstrap5\Form\View\Helper;

use Jield\Form\ElementInterface;
use Jield\Stdlib\Value;

/**
 * Class FormRadio
 * @package Jield\Bootstrap5\Form\View\Helper
 */
final class FormRadio extends FormMultiCheckbox
{
    protected static function getName(ElementInterface $element): string
    {
        return Value::string($element->getName());
    }

    protected function getInputType(): string
    {
        return 'radio';
    }
}
