<?php

declare(strict_types=1);

namespace Jield\Form\View\Helper;

use Jield\Form\ElementInterface;

/** @final */
class FormUrl extends FormInput
{
    /**
     * Attributes valid for the input tag type="url"
     *
     * @var array
     */
    protected array $validTagAttributes = [
        'name' => true,
        'autocomplete' => true,
        'autofocus' => true,
        'disabled' => true,
        'form' => true,
        'list' => true,
        'maxlength' => true,
        'minlength' => true,
        'pattern' => true,
        'placeholder' => true,
        'readonly' => true,
        'required' => true,
        'size' => true,
        'type' => true,
        'value' => true,
    ];

    /**
     * Determine input type to use
     */
    protected function getType(ElementInterface $element): string
    {
        return 'url';
    }
}
