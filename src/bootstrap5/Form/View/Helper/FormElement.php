<?php

declare(strict_types=1);

namespace Jield\Bootstrap5\Form\View\Helper;

use Jield\Form\ElementInterface;
use Jield\Form\LabelAwareInterface;
use Jield\Form\View\Helper;
use Jield\Stdlib\Service;
use Jield\Stdlib\Value;
use Laminas\Translator\TranslatorInterface;
use Laminas\View\Helper\EscapeHtml;
use Laminas\View\HelperPluginManager;
use Webmozart\Assert\Assert;

use function sprintf;

/**
 * Class FormElement
 * @package Jield\Bootstrap5\Form\View\Helper
 */
class FormElement extends Helper\FormElement
{
    public const string TYPE_HORIZONTAL = 'horizontal';
    public const string TYPE_INLINE = 'inline';
    public const string TYPE_DEFAULT = 'default';
    public const string TYPE_FLOATING_LABEL = 'floating_label';
    public const string TYPE_ELEMENT_ONLY = 'element_only';

    public const int ELEMENT_COLS_DEFAULT = 9;
    public const int ELEMENT_COLS_SMALL = 3;

    protected bool $isSelectPicker = false;

    protected bool $isRendered = false;

    private int $elementCols = self::ELEMENT_COLS_DEFAULT;

    protected array $typeMap = [
        'text' => 'lbs5forminput',
        'email' => 'lbs5forminput',
        'number' => 'lbs5forminput',
        'color' => 'lbs5forminput',
        'password' => 'lbs5forminput',
        'url' => 'lbs5forminput',
        'checkbox' => 'lbs5formcheckbox',
        'file' => 'lbs5formfile',
        'textarea' => 'lbs5formtextarea',
        'datetime-local' => 'lbs5formdatetimelocal',
        'radio' => 'lbs5formradio',
        'datetime' => 'lbs5forminput',
        'date' => 'lbs5forminput',
        'select' => 'lbs5formselect',
        'multi_checkbox' => 'lbs5formmulticheckbox',
    ];

    protected string $type = self::TYPE_HORIZONTAL;

    private ?Helper\FormLabel $formLabel = null;

    private ?EscapeHtml $escapeHtml = null;

    private ?FormDescription $formDescription = null;

    private ?Helper\FormElementErrors $formElementErrors = null;

    public function __construct(HelperPluginManager $viewHelperManager, TranslatorInterface $translator)
    {
        parent::__construct($viewHelperManager);
        $this->formLabel = Service::get($viewHelperManager, Helper\FormLabel::class, 'formlabel');
        $this->escapeHtml = Service::get($viewHelperManager, EscapeHtml::class, 'escapehtml');
        $this->formDescription = Service::get($viewHelperManager, FormDescription::class, 'lbs5formdescription');
        $this->formElementErrors = Service::get(
            $viewHelperManager,
            Helper\FormElementErrors::class,
            'formelementerrors',
        );

        $this->translator = $translator;
    }

    public function __invoke(
        ?ElementInterface $element = null,
        string|bool $type = self::TYPE_HORIZONTAL,
        bool $formElementOnly = false,
    ): string|static {
        //We previously has the type a boolean with $inline
        if ($type === true) {
            $type = self::TYPE_DEFAULT;
        } elseif ($type === false) {
            $type = self::TYPE_HORIZONTAL;
        }

        if ($formElementOnly) {
            $type = self::TYPE_ELEMENT_ONLY;
        }

        $this->type = $type;

        if ($element) {
            return $this->render(element: $element);
        }

        return $this;
    }

    public function render(ElementInterface $element): string
    {
        if ($element->getAttribute(key: 'type') === 'select') {
            if ($element->getOption(option: 'searchable')) {
                $element->setAttribute(key: 'class', value: 'form-control selectpicker');
                $element->setAttribute(key: 'data-live-search', value: 'true');

                //Enable the selectpicker
                $this->setIsSelectPicker(isSelectPicker: true);
            }

            if ($this->isSelectPicker && !$this->isRendered) {
                $this->getView()->headLink()->appendStylesheet('assets/css/jield-bootstrap5/bootstrap-select.css');
                $this
                    ->getView()
                    ->headScript()
                    ->appendFile('assets/js/jield-bootstrap5/bootstrap-select.js', [
                        'type' => 'text/javascript',
                    ] + []);

                $this->isRendered = true;
            }

            if ($this->isSelectPicker) {
                $element->setAttribute(key: 'class', value: 'form-control selectpicker');
                $element->setAttribute(key: 'data-live-search', value: 'true');
            }
        }

        if ($element->getOption(option: 'is-date-range')) {
            $this->getView()->headLink()->appendStylesheet('assets/css/jield-bootstrap5/daterangepicker.css');
            $this
                ->getView()
                ->headScript()
                ->appendFile('assets/js/jield-bootstrap5/daterangepicker.js', [
                    'type' => 'text/javascript',
                ] + []);

            $element->setAttribute(key: 'class', value: 'daterangepicker-element form-control');
            $element->setAttribute(key: 'placeholder', value: 'Click to set a date interval');
        }

        if ($element->getOption(option: 'has-codemirror')) {
            $this
                ->getView()
                ->headScript()
                ->appendFile('assets/js/jield-bootstrap5/codemirror.js', [
                    'type' => 'text/javascript',
                ] + []);
            $this->getView()->headLink()->appendStylesheet('assets/css/jield-bootstrap5/codemirror.css');

            $element->setAttribute(key: 'class', value: 'codemirror-element form-control');
            $element->setAttribute(key: 'data-mode', value: Value::plainScalar($element->getOption(option: 'mode')));
            $element->setAttribute(key: 'data-line-numbers', value: Value::plainScalar($element->getOption(option: 'line-numbers')));
            $element->setAttribute(key: 'data-height', value: Value::plainScalar($element->getOption(option: 'height')));
        }

        $renderedType = $this->renderType(element: $element);

        if ($renderedType !== null) {
            return $renderedType;
        }

        $element->setValue(value: Assert::notNull($this->translator)->translate(message: Assert::string($element->getValue())));

        return parent::render(element: $element);
    }

    protected function renderType(ElementInterface $element): ?string
    {
        $type = $element->getAttribute(key: 'type');

        if ($type !== null && isset($this->typeMap[$type])) {
            //Produce the label
            $label = $this->findLabel(element: $element);
            $renderedElement = $this->renderHelper(name: $this->typeMap[$type], element: $element);
            $description = $this->parseDescription(element: $element);
            $error = $this->hasFormElementError(element: $element)
                ? $this->parseFormElementError(element: $element)
                : null;

            if ($this->type === self::TYPE_ELEMENT_ONLY) {
                return sprintf('%s%s', $renderedElement, $error);
            }

            $this->elementCols = self::ELEMENT_COLS_DEFAULT;
            if (in_array(needle: $type, haystack: ['date', 'datetime-local'], strict: true)) {
                $this->elementCols = self::ELEMENT_COLS_SMALL;
            }

            switch ($type) {
                case 'radio':
                    return $this->getRadioElement(
                        label: Assert::string($label),
                        element: $renderedElement,
                        error: $error,
                        description: $description,
                    );
                case 'multi_checkbox':
                    return $this->getMultiCheckboxElement(
                        label: Assert::string($label),
                        element: $renderedElement,
                        error: $error,
                        description: $description,
                    );
                case 'checkbox':
                    return $this->getCheckboxElement(
                        element: $renderedElement,
                        error: $error,
                        description: $description,
                    );
                case 'submit':
                case 'button':
                    return $renderedElement;
            }

            $label = $this->parseLabel(element: $element);
            return $this->getDefaultElement(
                label: $label,
                element: $renderedElement,
                error: $error,
                description: $description,
            );
        }

        return null;
    }

    private function findLabel(ElementInterface $element): ?string
    {
        $label = $element->getAttribute(key: 'label') ?? $element->getLabel();

        if ($label !== null && null !== ($translator = Assert::notNull($this->formLabel)->getTranslator())) {
            $label = $translator->translate(message: Assert::string($label));
        }

        return $label === null ? null : (string) $label;
    }

    protected function parseDescription(ElementInterface $element): string
    {
        return Assert::notNull($this->formDescription)->__invoke(element: $element);
    }

    protected function hasFormElementError(ElementInterface $element): bool
    {
        return '' !== $this->parseFormElementError(element: $element);
    }

    protected function parseFormElementError(ElementInterface $element): string
    {
        Assert::notNull($this->formElementErrors)->setMessageOpenFormat(
            messageOpenFormat: '<div class="invalid-feedback"><span%s>',
        );
        Assert::notNull($this->formElementErrors)->setMessageSeparatorString(messageSeparatorString: '<br>');
        Assert::notNull($this->formElementErrors)->setMessageCloseString(messageCloseString: '</span></div>');

        return Assert::notNull($this->formElementErrors)->__invoke(element: $element);
    }

    private function getRadioElement(string $label, string $element, ?string $error, string $description): string
    {
        return match ($this->type) {
            self::TYPE_DEFAULT => sprintf('%s%s%s%s', $label, $element, $error, $description),
            default => sprintf(
                '<fieldset class="row mb-3">
                                    <legend class="col-form-label col-sm-3 pt-0">%s</legend>
                                    <div class="col-sm-%d">
                                        %s
                                        %s
                                        %s
                                    </div>
                             </fieldset>',
                $label,
                $this->elementCols,
                $element,
                $error,
                $description,
            ),
        };
    }

    private function getMultiCheckboxElement(
        string $label,
        string $element,
        ?string $error,
        string $description,
    ): string {
        return match ($this->type) {
            self::TYPE_DEFAULT => sprintf(
                '<div class="mb-3"><label class="form-label"><strong>%s</strong></label>%s%s%s</div>',
                $label,
                $element,
                $error,
                $description,
            ),
            default => sprintf(
                '<fieldset class="row mb-3">
                                    <legend class="col-form-label col-sm-3 pt-0">%s</legend>
                                    <div class="col-sm-%d">
                                        %s
                                        %s
                                        %s
                                    </div>
                             </fieldset>',
                $label,
                $this->elementCols,
                $element,
                $error,
                $description,
            ),
        };
    }

    private function getCheckboxElement(string $element, ?string $error, string $description): string
    {
        return match ($this->type) {
            self::TYPE_DEFAULT => sprintf('<div class="mb-3">%s%s%s</div>', $element, $error, $description),
            default => sprintf(
                '<div class="row mb-3">
                                    <div class="col-sm-%d offset-sm-3">
                                        %s
                                        %s
                                        %s
                                    </div>
                             </div>',
                $this->elementCols,
                $element,
                $error,
                $description,
            ),
        };
    }

    protected function parseLabel(ElementInterface $element): string
    {
        $label = $this->findLabel(element: $element);

        if (null === $label) {
            return '';
        }

        $openTagAttributes = ['for' => $element->getName()];

        if ($this->type === self::TYPE_HORIZONTAL) {
            $openTagAttributes['class'] = 'col-sm-3 col-form-label';
        }

        if ($element->hasAttribute(key: 'required')) {
            $openTagAttributes['class'] = ($openTagAttributes['class'] ?? '') . ' required';
        }

        $openTag = Assert::notNull($this->formLabel)->openTag(attributesOrElement: $openTagAttributes);

        if (!$element instanceof LabelAwareInterface || !$element->getLabelOption(key: 'disable_html_escape')) {
            /** @mago-var mixed $label */
            $label = Assert::notNull($this->escapeHtml)->__invoke(value: $label);
        }

        return $openTag . Value::stringify($label) . Assert::notNull($this->formLabel)->closeTag();
    }

    private function getDefaultElement($label, $element, $error, $description): string
    {
        return match ($this->type) {
            self::TYPE_INLINE, self::TYPE_DEFAULT => sprintf('%s%s%s%s', Value::scalar($label), Value::scalar($element), Value::scalar($error), Value::scalar($description)),
            self::TYPE_ELEMENT_ONLY => sprintf('%s%s%s', Value::scalar($element), Value::scalar($error), Value::scalar($description)),
            self::TYPE_FLOATING_LABEL => sprintf(
                '<div class="form-floating mb-3">%s%s%s%s</div>',
                Value::scalar($element),
                Value::scalar($label),
                Value::scalar($error),
                Value::scalar($description),
            ),
            default => sprintf(
                '<div class="row mb-3">%s<div class="col-sm-%d">%s%s%s</div></div>',
                Value::scalar($label),
                $this->elementCols,
                Value::scalar($element),
                Value::scalar($error),
                Value::scalar($description),
            ),
        };
    }

    public function setIsSelectPicker(bool $isSelectPicker): FormElement
    {
        $this->isSelectPicker = $isSelectPicker;

        return $this;
    }
}
