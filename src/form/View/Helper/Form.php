<?php

declare(strict_types=1);

namespace Jield\Form\View\Helper;

use Jield\Form\FieldsetInterface;
use Jield\Form\FormInterface;
use Jield\Mvc\View\Renderer\PhpRenderer;
use Jield\Stdlib\Value;
use Laminas\View\Helper\Doctype;

use function array_key_exists;
use function array_merge;
use function assert;
use function method_exists;
use function sprintf;

/**
 * View helper for rendering Form objects
 *
 * @final
 */
class Form extends AbstractHelper
{
    /**
     * Attributes valid for this tag (form)
     *
     * @var array
     */
    protected array $validTagAttributes = [
        'accept-charset' => true,
        'action' => true,
        'autocomplete' => true,
        'enctype' => true,
        'method' => true,
        'name' => true,
        'novalidate' => true,
        'target' => true,
    ];

    /**
     * Invoke as function
     *
     * @template T as null|FormInterface
     * @psalm-param T $form
     * @psalm-return (T is null ? static : string)
     * @return Form|string
     */
    public function __invoke(?FormInterface $form = null): string|static
    {
        if (!$form) {
            return $this;
        }

        return $this->render(form: $form);
    }

    /**
     * Render a form from the provided $form,
     */
    public function render(FormInterface $form): string
    {
        if (method_exists(object_or_class: $form, method: 'prepare')) {
            $form->prepare();
        }

        $formContent = '';

        $renderer = $this->getView();
        assert(assertion: $renderer instanceof PhpRenderer);
        foreach ($form as $element) {
            if ($element instanceof FieldsetInterface) {
                $formContent .= Value::stringify(Value::call(
                    $renderer,
                    'formCollection',
                    [$element],
                ));
            } else {
                $formContent .= Value::stringify(Value::call(
                    $renderer,
                    'formRow',
                    [$element],
                ));
            }
        }

        return $this->openTag(form: $form) . $formContent . $this->closeTag();
    }

    /**
     * Generate an opening form tag
     */
    public function openTag(?FormInterface $form = null): string
    {
        $doctype = $this->getDoctype();
        $attributes = [];

        if (!(Doctype::HTML5 === $doctype || Doctype::XHTML5 === $doctype)) {
            $attributes = [
                'action' => '',
                'method' => 'get',
            ];
        }

        if ($form instanceof FormInterface) {
            $formAttributes = $form->getAttributes();
            if (
                !array_key_exists(key: 'id', array: $formAttributes)
                && array_key_exists(key: 'name', array: $formAttributes)
            ) {
                $formAttributes['id'] = $formAttributes['name'];
            }
            $attributes = array_merge($attributes, $formAttributes);
        }

        if ($attributes) {
            return sprintf('<form %s>', $this->createAttributesString(attributes: $attributes));
        }

        return '<form>';
    }

    /**
     * Generate a closing form tag
     */
    public function closeTag(): string
    {
        return '</form>';
    }
}
