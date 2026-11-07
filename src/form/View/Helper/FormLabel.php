<?php

declare(strict_types=1);

namespace Jield\Form\View\Helper;

use Jield\Form\ElementInterface;
use Jield\Form\Exception;
use Jield\Form\LabelAwareInterface;

use function array_merge;
use function get_debug_type;
use function is_array;
use function sprintf;

/** @final */
class FormLabel extends AbstractHelper
{
    public const string APPEND = 'append';
    public const string PREPEND = 'prepend';

    /**
     * Attributes valid for the label tag
     *
     * @var array
     */
    protected array $validTagAttributes = [
        'for' => true,
        'form' => true,
    ];

    /**
     * Generate a form label, optionally with content
     *
     * Always generates a "for" statement, as we cannot assume the form input
     * will be provided in the $labelContent.
     *
     * @template T as null|ElementInterface
     * @psalm-param T $element
     * @psalm-return (T is null ? static : string)
     * @throws Exception\DomainException
     * @return string|FormLabel
     */
    public function __invoke(
        ?ElementInterface $element = null,
        ?string $labelContent = null,
        ?string $position = null,
    ): string|static {
        if (!$element) {
            return $this;
        }

        $openTag = $this->openTag(attributesOrElement: $element);
        $label = '';
        if ($labelContent === null || $position !== null) {
            $label = $element->getLabel();
            if (empty($label)) {
                throw new Exception\DomainException(message: sprintf(
                    '%s expects either label content as the second argument, '
                    . 'or that the element provided has a label attribute; neither found',
                    __METHOD__,
                ));
            }

            $label = $this->translateLabel(label: $label);
            $label = $this->escapeLabel(element: $element, label: $label);
        }

        if ($label && $labelContent) {
            switch ($position) {
                case self::APPEND:
                    $labelContent .= $label;
                    break;
                case self::PREPEND:
                default:
                    $labelContent = $label . $labelContent;
                    break;
            }
        }

        if ($label && null === $labelContent) {
            $labelContent = $label;
        }

        return $openTag . (string) $labelContent . $this->closeTag();
    }

    /**
     * Generate an opening label tag
     *
     * @param array|ElementInterface|null $attributesOrElement
     * @throws Exception\InvalidArgumentException
     * @throws Exception\DomainException
     */
    public function openTag(ElementInterface|array|null $attributesOrElement = null): string
    {
        if (null === $attributesOrElement || [] === $attributesOrElement) {
            return '<label>';
        }

        if (is_array(value: $attributesOrElement)) {
            $attributes = $this->createAttributesString(attributes: $attributesOrElement);
            return sprintf('<label %s>', $attributes);
        }

        $id = $this->getId(element: $attributesOrElement);
        if (null === $id) {
            throw new Exception\DomainException(message: sprintf(
                '%s expects the Element provided to have either a name or an id present; neither found',
                __METHOD__,
            ));
        }

        $labelAttributes = [];
        if ($attributesOrElement instanceof LabelAwareInterface) {
            $labelAttributes = $attributesOrElement->getLabelAttributes();
        }

        $attributes = ['for' => $id];

        if (!empty($labelAttributes)) {
            $attributes = array_merge($labelAttributes, $attributes);
        }

        $attributes = $this->createAttributesString(attributes: $attributes);
        return sprintf('<label %s>', $attributes);
    }

    /**
     * Return a closing label tag
     */
    public function closeTag(): string
    {
        return '</label>';
    }
}
