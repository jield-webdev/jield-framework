<?php

declare(strict_types=1);

namespace Jield\Form\View\Helper;

use Jield\Form\ElementInterface;
use Jield\Form\Exception;

use function sprintf;

/** @final */
class FormImage extends FormInput
{
    /**
     * Attributes valid for the input tag type="image"
     *
     * @var array
     */
    protected array $validTagAttributes = [
        'name'           => true,
        'alt'            => true,
        'autofocus'      => true,
        'disabled'       => true,
        'form'           => true,
        'formaction'     => true,
        'formenctype'    => true,
        'formmethod'     => true,
        'formnovalidate' => true,
        'formtarget'     => true,
        'height'         => true,
        'src'            => true,
        'type'           => true,
        'width'          => true,
    ];

    /**
     * Render a form <input> element from the provided $element
     *
     * @throws Exception\DomainException
     */
    public function render(ElementInterface $element): string
    {
        $src = $element->getAttribute(key: 'src');
        if (empty($src)) {
            throw new Exception\DomainException(message: sprintf(
                '%s requires that the element has an assigned src; none discovered',
                __METHOD__
            ));
        }

        return parent::render(element: $element);
    }

    /**
     * Determine input type to use
     */
    protected function getType(ElementInterface $element): string
    {
        return 'image';
    }
}
