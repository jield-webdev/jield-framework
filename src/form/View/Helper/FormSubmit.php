<?php

declare(strict_types=1);

namespace Jield\Form\View\Helper;

use Jield\Form\ElementInterface;

/** @final */
class FormSubmit extends FormInput
{
    /**
     * Attributes valid for the input tag type="submit"
     */
    protected array $validTagAttributes = [
        'name' => true,
        'autofocus' => true,
        'disabled' => true,
        'form' => true,
        'formaction' => true,
        'formenctype' => true,
        'formmethod' => true,
        'formnovalidate' => true,
        'formtarget' => true,
        'type' => true,
        'value' => true,
    ];

    /**
     * Translatable attributes
     *
     * @var array<string, bool>
     */
    protected array $translatableAttributes = [
        'value' => true,
    ];

    /**
     * Determine input type to use
     */
    protected function getType(ElementInterface $element): string
    {
        return 'submit';
    }
}
