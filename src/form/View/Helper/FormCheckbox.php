<?php

declare(strict_types=1);

namespace Jield\Form\View\Helper;

use Jield\Form\Element\Checkbox as CheckboxElement;
use Jield\Form\ElementInterface;
use Jield\Form\Exception;

use function sprintf;

class FormCheckbox extends FormInput
{
    /**
     * Render a form <input> element from the provided $element
     *
     * @throws Exception\InvalidArgumentException
     * @throws Exception\DomainException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function render(ElementInterface $element): string
    {
        if (!$element instanceof CheckboxElement) {
            throw new Exception\InvalidArgumentException(message: sprintf(
                '%s requires that the element is of type Jield\Form\Element\Checkbox',
                __METHOD__,
            ));
        }

        $name = $element->getName();
        if ($name === null || $name === '') {
            throw new Exception\DomainException(message: sprintf(
                '%s requires that the element has an assigned name; none discovered',
                __METHOD__,
            ));
        }

        $attributes = $element->getAttributes();
        $attributes['name'] = $name;
        $attributes['type'] = $this->getInputType();
        $attributes['value'] = $element->getCheckedValue();
        $closingBracket = $this->getInlineClosingBracket();

        if ($element->isChecked()) {
            $attributes['checked'] = 'checked';
        }

        $rendered = sprintf('<input %s%s', $this->createAttributesString(attributes: $attributes), $closingBracket);

        if ($element->useHiddenElement()) {
            $hiddenAttributes = [
                'disabled' => $attributes['disabled'] ?? false,
                'name' => $attributes['name'],
                'value' => $element->getUncheckedValue(),
            ];

            $rendered = sprintf(
                '<input type="hidden" %s%s%s',
                $this->createAttributesString(attributes: $hiddenAttributes),
                $closingBracket,
                $rendered,
            );
        }

        return $rendered;
    }

    /**
     * Return input type
     */
    protected function getInputType(): string
    {
        return 'checkbox';
    }
}
