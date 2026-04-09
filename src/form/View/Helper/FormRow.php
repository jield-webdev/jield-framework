<?php

declare(strict_types=1);

namespace Jield\Form\View\Helper;

use Jield\Form\Element\Button;
use Jield\Form\Element\Captcha;
use Jield\Form\Element\MonthSelect;
use Jield\Form\ElementInterface;
use Jield\Form\Exception;
use Jield\Form\LabelAwareInterface;

use function in_array;
use function method_exists;
use function sprintf;
use function strtolower;

/** @final */
class FormRow extends AbstractHelper
{
    public const string LABEL_APPEND  = 'append';
    public const string LABEL_PREPEND = 'prepend';

    /**
     * The class that is added to element that have errors
     *
     * @var string
     */
    protected string $inputErrorClass = 'input-error';

    /**
     * The attributes for the row label
     *
     * @var array
     */
    protected array $labelAttributes = [];

    /**
     * Where will be label rendered?
     *
     * @var string
     */
    protected string $labelPosition = self::LABEL_PREPEND;

    /**
     * Are the errors are rendered by this helper?
     *
     * @var bool
     */
    protected bool $renderErrors = true;

    /**
     * Form label helper instance
     *
     * @var null|FormLabel
     */
    protected ?FormLabel $labelHelper;

    /**
     * Form element helper instance
     *
     * @var null|FormElement
     */
    protected ?FormElement $elementHelper;

    /**
     * Form element errors helper instance
     *
     * @var null|FormElementErrors
     */
    protected ?FormElementErrors $elementErrorsHelper;

    /** @var null|string */
    protected ?string $partial;

    /**
     * Invoke helper as functor
     *
     * Proxies to {@link render()}.
     *
     * @template T as null|ElementInterface
     * @psalm-param T $element
     * @psalm-return (T is null ? self : string)
     * @return string|FormRow
     */
    public function __invoke(
        ?ElementInterface $element = null,
        ?string $labelPosition = null,
        ?bool $renderErrors = null,
        ?string $partial = null
    ): string|FormRow|static
    {
        if (! $element) {
            return $this;
        }

        if ($labelPosition === null) {
            $labelPosition = $this->getLabelPosition();
        }

        if ($renderErrors !== null) {
            $this->setRenderErrors(renderErrors: $renderErrors);
        }

        if ($partial !== null) {
            $this->setPartial(partial: $partial);
        }

        return $this->render(element: $element, labelPosition: $labelPosition);
    }

    /**
     * Utility form helper that renders a label (if it exists), an element and errors
     *
     * @throws Exception\DomainException
     */
    public function render(ElementInterface $element, ?string $labelPosition = null): string
    {
        $labelHelper         = $this->getLabelHelper();
        $elementHelper       = $this->getElementHelper();
        $elementErrorsHelper = $this->getElementErrorsHelper();

        $label           = $element->getLabel() ?? '';
        $inputErrorClass = $this->getInputErrorClass();

        if ($labelPosition === null) {
            $labelPosition = $this->labelPosition;
        }

        if ('' !== $label) {
            // Translate the label
            $label = $this->translateLabel(label: $label);
        }

        // Does this element have errors ?
        if ($element->getMessages() && $inputErrorClass) {
            $classAttributes  = $element->hasAttribute(key: 'class') ? (string) $element->getAttribute(key: 'class') . ' ' : '';
            $classAttributes .= $inputErrorClass;

            $element->setAttribute(key: 'class', value: $classAttributes);
        }

        if ($this->partial) {
            $vars = [
                'element'         => $element,
                'label'           => $label,
                'labelAttributes' => $this->labelAttributes,
                'labelPosition'   => $labelPosition,
                'renderErrors'    => $this->renderErrors,
            ];

            return $this->view->render($this->partial, $vars);
        }

        $elementErrors = '';
        if ($this->renderErrors) {
            $elementErrors = $elementErrorsHelper->render(element: $element);
        }

        $elementString = $elementHelper->render(element: $element);

        // hidden elements do not need a <label> -https://github.com/zendframework/zf2/issues/5607
        $type = $element->getAttribute(key: 'type');

        if ($label === '' || $type === 'hidden') {
            return $elementString . $elementErrors;
        }

        $labelAttributes = [];

        if ($element instanceof LabelAwareInterface) {
            $labelAttributes = $element->getLabelAttributes();
        }

        $label = $this->escapeLabel(element: $element, label: $label);

        if (empty($labelAttributes)) {
            $labelAttributes = $this->labelAttributes;
        }

        // Multicheckbox elements have to be handled differently as the HTML standard does not allow nested
        // labels. The semantic way is to group them inside a fieldset
        if (
            $type === 'multi_checkbox'
            || $type === 'radio'
            || $element instanceof MonthSelect
            || $element instanceof Captcha
        ) {
            $markup = sprintf(
                '<fieldset><legend>%s</legend>%s</fieldset>',
                $label,
                $elementString
            );

            return $markup . $elementErrors;
        }

        // Ensure element and label will be separated if element has an `id`-attribute.
        // If element has label option `always_wrap` it will be nested in any case.
        if (
            $element->hasAttribute(key: 'id')
            && ($element instanceof LabelAwareInterface && ! $element->getLabelOption(key: 'always_wrap'))
        ) {
            $labelOpen  = '';
            $labelClose = '';
            $label      = $labelHelper->openTag(attributesOrElement: $element) . $label . $labelHelper->closeTag();
        } else {
            $labelOpen  = $labelHelper->openTag(attributesOrElement: $labelAttributes);
            $labelClose = $labelHelper->closeTag();
        }

        if (
            $label !== '' && (! $element->hasAttribute(key: 'id'))
            || ($element instanceof LabelAwareInterface && $element->getLabelOption(key: 'always_wrap'))
        ) {
            $label = '<span>' . $label . '</span>';
        }

        // Button element is a special case, because label is always rendered inside it
        if ($element instanceof Button) {
            $labelOpen = $labelClose = $label = '';
        }

        if ($element instanceof LabelAwareInterface && $element->getLabelOption(key: 'label_position')) {
            $labelPosition = $element->getLabelOption(key: 'label_position');
        }

        $markup = match ($labelPosition) {
            self::LABEL_PREPEND => $labelOpen . $label . $elementString . $labelClose,
            default => $labelOpen . $elementString . $label . $labelClose,
        };

        return $markup . $elementErrors;
    }

    /**
     * Set the class that is added to element that have errors
     *
     * @return $this
     */
    public function setInputErrorClass(string $inputErrorClass): static
    {
        $this->inputErrorClass = $inputErrorClass;
        return $this;
    }

    /**
     * Get the class that is added to element that have errors
     */
    public function getInputErrorClass(): string
    {
        return $this->inputErrorClass;
    }

    /**
     * Set the attributes for the row label
     *
     * @return $this
     */
    public function setLabelAttributes(array $labelAttributes): static
    {
        $this->labelAttributes = $labelAttributes;
        return $this;
    }

    /**
     * Get the attributes for the row label
     */
    public function getLabelAttributes(): array
    {
        return $this->labelAttributes;
    }

    /**
     * Set the label position
     *
     * @throws Exception\InvalidArgumentException
     * @return $this
     */
    public function setLabelPosition(string $labelPosition): static
    {
        $labelPosition = strtolower(string: $labelPosition);
        if (! in_array(needle: $labelPosition, haystack: [self::LABEL_APPEND, self::LABEL_PREPEND])) {
            throw new Exception\InvalidArgumentException(message: sprintf(
                '%s expects either %s::LABEL_APPEND or %s::LABEL_PREPEND; received "%s"',
                __METHOD__,
                self::class,
                self::class,
                $labelPosition
            ));
        }
        $this->labelPosition = $labelPosition;

        return $this;
    }

    /**
     * Get the label position
     */
    public function getLabelPosition(): string
    {
        return $this->labelPosition;
    }

    /**
     * Set if the errors are rendered by this helper
     *
     * @return $this
     */
    public function setRenderErrors(bool $renderErrors): static
    {
        $this->renderErrors = $renderErrors;
        return $this;
    }

    /**
     * Retrieve if the errors are rendered by this helper
     */
    public function getRenderErrors(): bool
    {
        return $this->renderErrors;
    }

    /**
     * Set a partial view script to use for rendering the row
     *
     * @return $this
     */
    public function setPartial(?string $partial): static
    {
        $this->partial = $partial;
        return $this;
    }

    /**
     * Retrieve current partial
     */
    public function getPartial(): ?string
    {
        return $this->partial;
    }

    /**
     * Retrieve the FormLabel helper
     */
    protected function getLabelHelper(): FormLabel
    {
        if ($this->labelHelper) {
            return $this->labelHelper;
        }

        if ($this->view !== null && method_exists(object_or_class: $this->view, method: 'plugin')) {
            $this->labelHelper = $this->view->plugin('form_label');
        }

        if (! $this->labelHelper instanceof FormLabel) {
            $this->labelHelper = new FormLabel();
        }

        if ($this->hasTranslator()) {
            $this->labelHelper->setTranslator(
                $this->getTranslator(),
                $this->getTranslatorTextDomain()
            );
        }

        return $this->labelHelper;
    }

    /**
     * Retrieve the FormElement helper
     */
    protected function getElementHelper(): FormElement
    {
        if ($this->elementHelper) {
            return $this->elementHelper;
        }

        if ($this->view !== null && method_exists(object_or_class: $this->view, method: 'plugin')) {
            $this->elementHelper = $this->view->plugin('form_element');
        }

        if (! $this->elementHelper instanceof FormElement) {
            $this->elementHelper = new FormElement();
        }

        return $this->elementHelper;
    }

    /**
     * Retrieve the FormElementErrors helper
     */
    protected function getElementErrorsHelper(): FormElementErrors
    {
        if ($this->elementErrorsHelper) {
            return $this->elementErrorsHelper;
        }

        if ($this->view !== null && method_exists(object_or_class: $this->view, method: 'plugin')) {
            $this->elementErrorsHelper = $this->view->plugin('form_element_errors');
        }

        if (! $this->elementErrorsHelper instanceof FormElementErrors) {
            $this->elementErrorsHelper = new FormElementErrors();
        }

        return $this->elementErrorsHelper;
    }
}
