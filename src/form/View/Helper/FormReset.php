<?php

declare(strict_types=1);

namespace Jield\Form\View\Helper;

use Jield\Form\ElementInterface;
use Jield\Form\Exception;

/** @final */
class FormReset extends FormInput
{
    /**
     * Attributes valid for the input tag type="reset"
     *
     * @var array
     */
    protected array $validTagAttributes = [
        'name' => true,
        'autofocus' => true,
        'disabled' => true,
        'form' => true,
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
     *
     * @throws Exception\DomainException
     */
    protected function getType(ElementInterface $element): string
    {
        return 'reset';
    }
}
