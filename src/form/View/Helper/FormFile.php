<?php

declare(strict_types=1);

namespace Jield\Form\View\Helper;

use Jield\Form\ElementInterface;
use Jield\Form\Exception;

use function array_key_exists;
use function is_array;
use function is_string;
use function sprintf;

class FormFile extends FormInput
{
    /**
     * Attributes valid for the input tag type="file"
     */
    protected array $validTagAttributes = [
        'name' => true,
        'accept' => true,
        'autofocus' => true,
        'disabled' => true,
        'form' => true,
        'multiple' => true,
        'required' => true,
        'type' => true,
    ];

    /**
     * Render a form <input> element from the provided $element
     *
     * @throws Exception\DomainException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function render(ElementInterface $element): string
    {
        $name = $element->getName();
        if ($name === null || $name === '') {
            throw new Exception\DomainException(message: sprintf(
                '%s requires that the element has an assigned name; none discovered',
                __METHOD__,
            ));
        }

        $attributes = $element->getAttributes();
        $attributes['type'] = $this->getType(element: $element);
        $attributes['name'] = $name;
        if (array_key_exists(key: 'multiple', array: $attributes) && $attributes['multiple']) {
            $attributes['name'] .= '[]';
        }

        /** @mago-var mixed $value */
        $value = $element->getValue();
        if (is_array(value: $value) && isset($value['name']) && !is_array(value: $value['name'])) {
            $attributes['value'] = $value['name'];
        } elseif (is_string(value: $value)) {
            $attributes['value'] = $value;
        }

        return sprintf(
            '<input %s%s',
            $this->createAttributesString(attributes: $attributes),
            $this->getInlineClosingBracket(),
        );
    }

    /**
     * Determine input type to use
     */
    protected function getType(ElementInterface $element): string
    {
        return 'file';
    }
}
