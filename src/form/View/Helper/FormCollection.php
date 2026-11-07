<?php

declare(strict_types=1);

namespace Jield\Form\View\Helper;

use Jield\Form\Element\Collection as CollectionElement;
use Jield\Form\ElementInterface;
use Jield\Form\FieldsetInterface;
use Jield\Stdlib\Value;
use Laminas\View\Helper\HelperInterface;
use RuntimeException;

use function assert;
use function is_callable;
use function method_exists;
use function sprintf;

/** @final */
class FormCollection extends AbstractHelper
{
    /**
     * Attributes valid for this tag (form)
     *
     * @var array
     */
    protected array $validTagAttributes = [
        'name' => true,
        'disabled' => true,
        'form' => true,
    ];

    /**
     * If set to true, collections are automatically wrapped around a fieldset
     *
     * @var bool
     */
    protected bool $shouldWrap = true;

    /**
     * This is the default wrapper that the collection is wrapped into
     *
     * @var string
     */
    protected string $wrapper = '<fieldset%4$s>%2$s%1$s%3$s</fieldset>';

    /**
     * This is the default label-wrapper
     *
     * @var string
     */
    protected string $labelWrapper = '<legend>%s</legend>';

    /**
     * Where shall the template-data be inserted into
     *
     * @var string
     */
    protected string $templateWrapper = '<span data-template="%s"></span>';

    /**
     * The name of the default view helper that is used to render sub elements.
     *
     * @var string
     */
    protected string $defaultElementHelper = 'formrow';

    /**
     * The view helper used to render sub elements.
     *
     * @var null|HelperInterface
     */
    protected ?HelperInterface $elementHelper = null;

    /**
     * The view helper used to render sub fieldsets.
     *
     * @var null|HelperInterface
     */
    protected ?HelperInterface $fieldsetHelper = null;

    /**
     * Invoke helper as function
     *
     * Proxies to {@link render()}.
     *
     * @template T as null|ElementInterface
     * @psalm-param T $element
     * @psalm-return (T is null ? static : string)
     * @return string|FormCollection
     */
    public function __invoke(?ElementInterface $element = null, bool $wrap = true): string|static
    {
        if (!$element) {
            return $this;
        }

        $this->setShouldWrap(wrap: $wrap);

        return $this->render(element: $element);
    }

    /**
     * Render a collection by iterating through all fieldsets and elements
     */
    public function render(ElementInterface $element): string
    {
        $renderer = $this->getView();
        if (!method_exists(object_or_class: $renderer, method: 'plugin')) {
            // Bail early if renderer is not pluggable
            return '';
        }

        $markup = '';
        $templateMarkup = '';
        $elementHelper = $this->getElementHelper();
        assert(assertion: is_callable(value: $elementHelper));

        $fieldsetHelper = $this->getFieldsetHelper();
        assert(assertion: is_callable(value: $fieldsetHelper));

        if ($element instanceof CollectionElement && $element->shouldCreateTemplate()) {
            $templateMarkup = $this->renderTemplate(collection: $element);
        }

        $element = Value::instance($element, FieldsetInterface::class);
        foreach ($element->getIterator() as $elementOrFieldset) {
            if ($elementOrFieldset instanceof FieldsetInterface) {
                $markup .= Value::stringify($fieldsetHelper($elementOrFieldset, $this->shouldWrap()));
            } else {
                $markup .= Value::stringify($elementHelper($elementOrFieldset));
            }
        }

        if (!$this->shouldWrap) {
            return $markup . $templateMarkup;
        }

        // Every collection is wrapped by a fieldset if needed
        $attributes = $element->getAttributes();
        if (!$this->getDoctypeHelper()->isHtml5()) {
            unset($attributes['name'], $attributes['disabled'], $attributes['form']);
        }
        $attributesString = $attributes !== [] ? ' ' . $this->createAttributesString(attributes: $attributes) : '';

        $label = $element->getLabel();
        $legend = '';

        if (!empty($label)) {
            $label = $this->translateLabel(label: $label);
            $label = $this->escapeLabel(element: $element, label: $label);

            $legend = sprintf($this->labelWrapper, $label);
        }

        return sprintf($this->wrapper, $markup, $legend, $templateMarkup, $attributesString);
    }

    /**
     * Only render a template
     */
    public function renderTemplate(CollectionElement $collection): string
    {
        $elementHelper = $this->getElementHelper();
        assert(assertion: is_callable(value: $elementHelper));
        $escapeHtmlAttribHelper = $this->getEscapeHtmlAttrHelper();
        $fieldsetHelper = $this->getFieldsetHelper();
        assert(assertion: is_callable(value: $fieldsetHelper));

        $templateMarkup = '';

        $elementOrFieldset = $collection->getTemplateElement();

        if ($elementOrFieldset instanceof FieldsetInterface) {
            $templateMarkup .= Value::stringify($fieldsetHelper($elementOrFieldset, $this->shouldWrap()));
        } else {
            $templateMarkup .= Value::stringify($elementHelper($elementOrFieldset));
        }

        return sprintf(
            $this->getTemplateWrapper(),
            Value::scalar($escapeHtmlAttribHelper(value: $templateMarkup)),
        );
    }

    /**
     * If set to true, collections are automatically wrapped around a fieldset
     *
     * @return static
     */
    public function setShouldWrap(bool $wrap): static
    {
        $this->shouldWrap = $wrap;
        return $this;
    }

    /**
     * Get wrapped
     */
    public function shouldWrap(): bool
    {
        return $this->shouldWrap;
    }

    /**
     * Sets the name of the view helper that should be used to render sub elements.
     *
     * @param  string $defaultSubHelper The name of the view helper to set.
     * @return static
     */
    public function setDefaultElementHelper(string $defaultSubHelper): static
    {
        $this->defaultElementHelper = $defaultSubHelper;
        return $this;
    }

    /**
     * Gets the name of the view helper that should be used to render sub elements.
     */
    public function getDefaultElementHelper(): string
    {
        return $this->defaultElementHelper;
    }

    /**
     * Sets the element helper that should be used by this collection.
     *
     * @param  HelperInterface $elementHelper The element helper to use.
     * @return static
     */
    public function setElementHelper(HelperInterface $elementHelper): static
    {
        $this->elementHelper = $elementHelper;
        return $this;
    }

    /**
     * Retrieve the element helper.
     *
     * @throws \RuntimeException
     */
    protected function getElementHelper(): HelperInterface
    {
        if ($this->elementHelper) {
            return $this->elementHelper;
        }

        if ($this->view !== null && method_exists(object_or_class: $this->view, method: 'plugin')) {
            $this->elementHelper = Value::nullableInstance(
                $this->getView()->plugin($this->getDefaultElementHelper()),
                \Laminas\View\Helper\HelperInterface::class,
            );
        }

        if (!$this->elementHelper instanceof HelperInterface) {
            throw new RuntimeException(
                message: 'Invalid element helper set in FormCollection. The helper must be an '
                . 'instance of Laminas\View\Helper\HelperInterface.',
            );
        }

        return $this->elementHelper;
    }

    /**
     * Sets the fieldset helper that should be used by this collection.
     *
     * @param  HelperInterface $fieldsetHelper The fieldset helper to use.
     * @return static
     */
    public function setFieldsetHelper(HelperInterface $fieldsetHelper): static
    {
        $this->fieldsetHelper = $fieldsetHelper;
        return $this;
    }

    /**
     * Retrieve the fieldset helper.
     */
    protected function getFieldsetHelper(): HelperInterface
    {
        if ($this->fieldsetHelper) {
            return $this->fieldsetHelper;
        }

        return $this;
    }

    /**
     * Get the wrapper for the collection
     */
    public function getWrapper(): string
    {
        return $this->wrapper;
    }

    /**
     * Set the wrapper for this collection
     *
     * The string given will be passed through sprintf with the following three
     * replacements:
     *
     * 1. The content of the collection
     * 2. The label of the collection. If no label is given this will be an empty
     *   string
     * 3. The template span-tag. This might also be an empty string
     *
     * The preset default is <pre><fieldset>%2$s%1$s%3$s</fieldset></pre>
     *
     * @return static
     */
    public function setWrapper(string $wrapper): static
    {
        $this->wrapper = $wrapper;

        return $this;
    }

    /**
     * Set the label-wrapper
     * The string will be passed through sprintf with the label as single
     * parameter
     * This defaults to '<legend>%s</legend>'
     *
     * @return static
     */
    public function setLabelWrapper(string $labelWrapper): static
    {
        $this->labelWrapper = $labelWrapper;

        return $this;
    }

    /**
     * Get the wrapper for the label
     */
    public function getLabelWrapper(): string
    {
        return $this->labelWrapper;
    }

    /**
     * Ge the wrapper for the template
     */
    public function getTemplateWrapper(): string
    {
        return $this->templateWrapper;
    }

    /**
     * Set the string where the template will be inserted into
     *
     * This string will be passed through sprintf and has the template as single
     * parameter
     *
     * THis defaults to '<span data-template="%s"></span>'
     *
     * @return static
     */
    public function setTemplateWrapper(string $templateWrapper): static
    {
        $this->templateWrapper = $templateWrapper;

        return $this;
    }
}
