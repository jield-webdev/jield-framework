<?php

declare(strict_types=1);

namespace Jield\Form\View\Helper;

use Jield\Form\ElementInterface;

/** @final */
class FormHidden extends FormInput
{
    /**
     * Attributes valid for the input tag type="hidden"
     *
     * @var array
     */
    protected array $validTagAttributes = [
        'name' => true,
        'disabled' => true,
        'form' => true,
        'type' => true,
        'value' => true,
        'autocomplete' => true,
    ];

    /**
     * Determine input type to use
     */
    protected function getType(ElementInterface $element): string
    {
        return 'hidden';
    }
}
