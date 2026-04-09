<?php

declare(strict_types=1);

namespace Jield\Form\View\Helper;

use Jield\Form\ElementInterface;
use Jield\Form\Exception;

use function get_debug_type;
use function is_array;
use function is_string;
use function sprintf;
use function strtolower;

/** @final */
class FormButton extends FormInput
{
    /**
     * Attributes valid for the button tag
     *
     * @var array
     */
    protected array $validTagAttributes = [
        'name'           => true,
        'autofocus'      => true,
        'disabled'       => true,
        'form'           => true,
        'formaction'     => true,
        'formenctype'    => true,
        'formmethod'     => true,
        'formnovalidate' => true,
        'formtarget'     => true,
        'type'           => true,
        'value'          => true,
    ];

    /**
     * Valid values for the button type
     *
     * @var array
     */
    protected array $validTypes = [
        'button' => true,
        'reset'  => true,
        'submit' => true,
    ];

    /**
     * Invoke helper as functor
     *
     * Proxies to {@link render()}.
     *
     * @template T as null|ElementInterface
     * @psalm-param T $element
     * @psalm-return (T is null ? self : string)
     * @return string|FormButton
     */
    public function __invoke(?ElementInterface $element = null, ?string $buttonContent = null): FormButton|string|static
    {
        if (! $element) {
            return $this;
        }

        return $this->render(element: $element, buttonContent: $buttonContent);
    }

    /**
     * Render a form <button> element from the provided $element,
     * using content from $buttonContent or the element's "label" attribute
     *
     * @throws Exception\DomainException
     */
    public function render(ElementInterface $element, ?string $buttonContent = null): string
    {
        $openTag = $this->openTag(attributesOrElement: $element);

        if (null === $buttonContent) {
            $buttonContent = $element->getLabel();
            if (null === $buttonContent) {
                throw new Exception\DomainException(
                    message: sprintf(
                        '%s expects either button content as the second argument, '
                        . 'or that the element provided has a label value; neither found',
                        __METHOD__
                    )
                );
            }
        }

        $buttonContent = $this->translateLabel(label: $buttonContent);
        $buttonContent = $this->escapeLabel(element: $element, label: $buttonContent);

        return $openTag . $buttonContent . $this->closeTag();
    }

    /**
     * Generate an opening button tag
     *
     * @param array|ElementInterface|null $attributesOrElement
     * @throws Exception\InvalidArgumentException
     * @throws Exception\DomainException
     */
    public function openTag(ElementInterface|array|null $attributesOrElement = null): string
    {
        if (null === $attributesOrElement) {
            return '<button>';
        }

        if (is_array(value: $attributesOrElement)) {
            $attributes = $this->createAttributesString(attributes: $attributesOrElement);
            return sprintf('<button %s>', $attributes);
        }

        if (! $attributesOrElement instanceof ElementInterface) {
            throw new Exception\InvalidArgumentException(message: sprintf(
                '%s expects an array or Jield\Form\ElementInterface instance; received "%s"',
                __METHOD__,
                get_debug_type(value: $attributesOrElement)
            ));
        }

        $element = $attributesOrElement;
        $name    = $element->getName();
        if ($name === null || $name === '') {
            throw new Exception\DomainException(message: sprintf(
                '%s requires that the element has an assigned name; none discovered',
                __METHOD__
            ));
        }

        $attributes          = $element->getAttributes();
        $attributes['name']  = $name;
        $attributes['type']  = $this->getType(element: $element);
        $attributes['value'] = $element->getValue();

        return sprintf(
            '<button %s>',
            $this->createAttributesString(attributes: $attributes)
        );
    }

    /**
     * Return a closing button tag
     */
    public function closeTag(): string
    {
        return '</button>';
    }

    /**
     * Determine button type to use
     */
    protected function getType(ElementInterface $element): string
    {
        $type = $element->getAttribute(key: 'type');
        if (! is_string(value: $type) || $type === '') {
            return 'submit';
        }

        $type = strtolower(string: $type);
        if (! isset($this->validTypes[$type])) {
            return 'submit';
        }

        return $type;
    }
}
