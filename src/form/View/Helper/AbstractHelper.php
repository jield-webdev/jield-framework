<?php

declare(strict_types=1);

namespace Jield\Form\View\Helper;

use Jield\Form\ElementInterface;
use Jield\Form\Exception\InvalidArgumentException;
use Jield\Form\LabelAwareInterface;
use Jield\Mvc\View\Helper\AbstractTranslatorHelper as BaseAbstractHelper;
use Jield\Stdlib\Value;
use Laminas\Escaper\Escaper;
use Laminas\Escaper\Exception\RuntimeException as EscaperException;
use Laminas\View\Helper\Doctype;
use Laminas\View\Helper\EscapeHtml;
use Laminas\View\Helper\EscapeHtmlAttr;
use Webmozart\Assert\Assert;

use function implode;
use function in_array;
use function is_bool;
use function is_string;
use function mb_strpos;
use function preg_match;
use function sprintf;
use function str_starts_with;
use function strtolower;

/**
 * Base functionality for all form view helpers
 */
// Preserve the existing public API and the state and operations that implement it.
// @mago-expect lint:cyclomatic-complexity(1)
// @mago-expect lint:kan-defect(1)
// @mago-expect lint:too-many-methods(1)
// @mago-expect lint:too-many-properties(1)
abstract class AbstractHelper extends BaseAbstractHelper
{
    protected string $encoding = 'utf-8';

    /**
     * The default translatable HTML attributes
     */
    protected static array $defaultTranslatableHtmlAttributes = [
        'title' => true,
    ];

    /**
     * The default translatable HTML attribute prefixes
     */
    protected static array $defaultTranslatableHtmlAttributePrefixes = [];

    /**
     * Standard boolean attributes, with expected values for enabling/disabling
     */
    protected array $booleanAttributes = [
        'autofocus' => ['on' => 'autofocus', 'off' => ''], // https://html.spec.whatwg.org/#attr-fe-autofocus
        'checked' => ['on' => 'checked', 'off' => ''], // https://html.spec.whatwg.org/#attr-input-checked
        'disabled' => ['on' => 'disabled', 'off' => ''], // https://html.spec.whatwg.org/#attr-fe-disabled
        'itemscope' => ['on' => 'itemscope', 'off' => ''], // https://html.spec.whatwg.org/#attr-itemscope
        'multiple' => ['on' => 'multiple', 'off' => ''], // https://html.spec.whatwg.org/#attr-input-multiple
        'readonly' => ['on' => 'readonly', 'off' => ''], // https://html.spec.whatwg.org/#attr-input-readonly
        'required' => ['on' => 'required', 'off' => ''], // https://html.spec.whatwg.org/#attr-input-required
        'selected' => ['on' => 'selected', 'off' => ''], // https://html.spec.whatwg.org/#attr-option-selected
        'novalidate' => ['on' => 'novalidate', 'off' => ''], // https://html.spec.whatwg.org/#attr-fs-novalidate
    ];

    /**
     * Translatable attributes
     *
     * @var array<string, bool>
     */
    protected array $translatableAttributes = [
        'placeholder' => true,
    ];

    /**
     * Prefixes of translatable HTML attributes
     */
    protected array $translatableAttributePrefixes = [];

    protected ?Doctype $doctypeHelper = null;

    protected ?EscapeHtml $escapeHtmlHelper = null;

    protected ?EscapeHtmlAttr $escapeHtmlAttrHelper = null;

    /**
     * Attributes globally valid for all tags
     */
    protected array $validGlobalAttributes = [
        'accesskey' => true, // https://html.spec.whatwg.org/#the-accesskey-attribute
        'autocapitalize' => true, // https://html.spec.whatwg.org/#attr-autocapitalize
        'class' => true, // https://html.spec.whatwg.org/#classes
        'contenteditable' => true, // https://html.spec.whatwg.org/#contenteditable
        'contextmenu' => true, // Obsolete: https://html.spec.whatwg.org/#attr-contextmenu
        'dir' => true, // https://html.spec.whatwg.org/#the-dir-attribute
        'draggable' => true, // https://html.spec.whatwg.org/#the-draggable-attribute
        'dropzone' => true, // Obsolete: https://html.spec.whatwg.org/#attr-dropzone
        'enterkeyhint' => true, // https://html.spec.whatwg.org/#input-modalities:-the-enterkeyhint-attribute
        'hidden' => true, // https://html.spec.whatwg.org/#the-hidden-attribute
        'id' => true, // https://html.spec.whatwg.org/#the-id-attribute
        'inputmode' => true, // https://html.spec.whatwg.org/#attr-inputmode
        'is' => true, // https://html.spec.whatwg.org/#attr-is
        //'itemid'             => true, // https://html.spec.whatwg.org/#attr-itemid
        'itemprop' => true, // https://html.spec.whatwg.org/#names:-the-itemprop-attribute
        'itemref' => true, // https://html.spec.whatwg.org/#attr-itemref
        'itemscope' => true, // https://html.spec.whatwg.org/#attr-itemscope
        'itemtype' => true, // https://html.spec.whatwg.org/#attr-itemtype
        'lang' => true, // https://html.spec.whatwg.org/#the-lang-and-xml:lang-attributes
        //'nonce'              => true, // https://html.spec.whatwg.org/#attr-nonce
        'onabort' => true, // https://html.spec.whatwg.org/#handler-onabort
        'onblur' => true, // https://html.spec.whatwg.org/#handler-onblur
        'oncanplay' => true, // https://html.spec.whatwg.org/#handler-oncanplay
        'oncanplaythrough' => true, // https://html.spec.whatwg.org/#handler-oncanplaythrough
        'onchange' => true, // https://html.spec.whatwg.org/#handler-onchange
        'onclick' => true, // https://html.spec.whatwg.org/#handler-onclick
        'oncontextmenu' => true, // https://html.spec.whatwg.org/#handler-oncontextmenu
        'ondblclick' => true, // https://html.spec.whatwg.org/#handler-ondblclick
        'ondrag' => true, // https://html.spec.whatwg.org/#handler-ondrag
        'ondragend' => true, // https://html.spec.whatwg.org/#handler-ondragend
        'ondragenter' => true, // https://html.spec.whatwg.org/#handler-ondragenter
        'ondragleave' => true, // https://html.spec.whatwg.org/#handler-ondragleave
        'ondragover' => true, // https://html.spec.whatwg.org/#handler-ondragover
        'ondragstart' => true, // https://html.spec.whatwg.org/#handler-ondragstart
        'ondrop' => true, // https://html.spec.whatwg.org/#handler-ondrop
        'ondurationchange' => true, // https://html.spec.whatwg.org/#handler-ondurationchange
        'onemptied' => true, // https://html.spec.whatwg.org/#handler-onemptied
        'onended' => true, // https://html.spec.whatwg.org/#handler-onended
        'onerror' => true, // https://html.spec.whatwg.org/#handler-onerror
        'onfocus' => true, // https://html.spec.whatwg.org/#handler-onfocus
        'onfocusin' => true, // https://developer.mozilla.org/fr/docs/Web/API/Element/focusin_event
        'onfocusout' => true, // https://developer.mozilla.org/fr/docs/Web/API/Element/focusout_event
        'oninput' => true, // https://html.spec.whatwg.org/#handler-oninput
        'oninvalid' => true, // https://html.spec.whatwg.org/#handler-oninvalid
        'onkeydown' => true, // https://html.spec.whatwg.org/#handler-onkeydown
        'onkeypress' => true, // https://html.spec.whatwg.org/#handler-onkeypress
        'onkeyup' => true, // https://html.spec.whatwg.org/#handler-onkeyup
        'onload' => true, // https://html.spec.whatwg.org/#handler-onload
        'onloadeddata' => true, // https://html.spec.whatwg.org/#handler-onloadeddata
        'onloadedmetadata' => true, // https://html.spec.whatwg.org/#handler-onloadedmetadata
        'onloadstart' => true, // https://html.spec.whatwg.org/#handler-onloadstart
        'onmousedown' => true, // https://html.spec.whatwg.org/#handler-onmousedown
        'onmousemove' => true, // https://html.spec.whatwg.org/#handler-onmousemove
        'onmouseout' => true, // https://html.spec.whatwg.org/#handler-onmouseout
        'onmouseover' => true, // https://html.spec.whatwg.org/#handler-onmouseover
        'onmouseup' => true, // https://html.spec.whatwg.org/#handler-onmouseup
        'onmousewheel' => true, // https://html.spec.whatwg.org/#handler-onmousewheel
        'onpause' => true, // https://html.spec.whatwg.org/#handler-onpause
        'onplay' => true, // https://html.spec.whatwg.org/#handler-onplay
        'onplaying' => true, // https://html.spec.whatwg.org/#handler-onplaying
        'onprogress' => true, // https://html.spec.whatwg.org/#handler-onprogress
        'onratechange' => true, // https://html.spec.whatwg.org/#handler-onratechange
        'onreadystatechange' => true, // https://html.spec.whatwg.org/#handler-onreadystatechange
        'onreset' => true, // https://html.spec.whatwg.org/#handler-onreset
        'onscroll' => true, // https://html.spec.whatwg.org/#handler-onscroll
        'onseeked' => true, // https://html.spec.whatwg.org/#handler-onseeked
        'onseeking' => true, // https://html.spec.whatwg.org/#handler-onseeking
        'onselect' => true, // https://html.spec.whatwg.org/#handler-onselect
        'onshow' => true, // https://html.spec.whatwg.org/#handler-onshow
        'onstalled' => true, // https://html.spec.whatwg.org/#handler-onstalled
        'onsubmit' => true, // https://html.spec.whatwg.org/#handler-onsubmit
        'onsuspend' => true, // https://html.spec.whatwg.org/#handler-onsuspend
        'ontimeupdate' => true, // https://html.spec.whatwg.org/#handler-ontimeupdate
        'onvolumechange' => true, // https://html.spec.whatwg.org/#handler-onvolumechange
        'onwaiting' => true, // https://html.spec.whatwg.org/#handler-onwaiting
        'role' => true, // https://html.spec.whatwg.org/#attr-aria-role
        'slot' => true, // https://html.spec.whatwg.org/#attr-slot
        'spellcheck' => true, // https://html.spec.whatwg.org/#attr-spellcheck
        'style' => true, // https://html.spec.whatwg.org/#attr-style
        'tabindex' => true, // https://html.spec.whatwg.org/#attr-tabindex
        'title' => true, // https://html.spec.whatwg.org/#attr-title
        //'translate'          => true, // https://html.spec.whatwg.org/#attr-translate
        'xml:base' => true, // https://www.w3.org/TR/xmlbase/#syntax
        'xml:lang' => true, // https://html.spec.whatwg.org/#the-lang-and-xml:lang-attributes
        'xml:space' => true, // https://www.w3.org/TR/xml/#sec-white-space
    ];

    /**
     * Attribute prefixes valid for all tags
     */
    protected array $validTagAttributePrefixes = [
        'data-', // https://html.spec.whatwg.org/#attr-data-*
        'aria-', // https://html.spec.whatwg.org/#attr-aria-*
        'x-',
    ];

    /**
     * Attributes valid for the tag represented by this helper
     *
     * This should be overridden in extending classes
     */
    protected array $validTagAttributes = [];

    /**
     * Set value for doctype
     */
    public function setDoctype(string $doctype): static
    {
        $this->doctypeHelper = new Doctype($doctype);
        return $this;
    }

    /**
     * Get value for doctype
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function getDoctype(): string
    {
        $doctype = $this->getDoctypeHelper();
        if ($doctype->isHtml5()) {
            return $doctype->isXhtml() ? Doctype::XHTML5 : Doctype::HTML5;
        }
        return $doctype->isXhtml() ? Doctype::XHTML1_STRICT : Doctype::HTML4_STRICT;
    }

    /**
     * Set value for character encoding
     */
    public function setEncoding(string $encoding): static
    {
        $escaper = new Escaper(Assert::nullOrStringNotEmpty($encoding));
        $this->escapeHtmlHelper = new EscapeHtml($escaper);
        $this->escapeHtmlAttrHelper = new EscapeHtmlAttr($escaper);
        $this->encoding = $encoding;
        return $this;
    }

    /**
     * Get character encoding
     */
    public function getEncoding(): string
    {
        return $this->encoding;
    }

    /**
     * Create a string of all attribute/value pairs
     *
     * Escapes all attribute values
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function createAttributesString(array $attributes): string
    {
        $attributes = $this->prepareAttributes(attributes: $attributes);
        $escape = $this->getEscapeHtmlHelper();
        $escapeAttr = $this->getEscapeHtmlAttrHelper();
        $doctypeHelper = $this->getDoctypeHelper();
        $strings = [];

        /** @mago-var mixed $value */
        foreach ($attributes as $key => $value) {
            $key = strtolower(string: (string) $key);

            if (($this->booleanAttributes[$key] ?? null) !== null) {
                // These alternatives are mutually exclusive; the following code consumes the selected state.
                // @mago-expect lint:no-else-clause(1)
                if (!$value) {
                    // Skip boolean attributes that expect empty string as false value
                    if ('' === Value::accessible($this->booleanAttributes[$key])['off']) {
                        continue;
                    }
                } elseif ($doctypeHelper->isHtml5() && !$doctypeHelper->isXhtml()) {
                    $strings[] = $escape(value: $key);
                    continue;
                }
            }

            //check if attribute is translatable and translate it
            $value = $this->translateHtmlAttributeValue(key: $key, value: Assert::nullOrString($value));

            // @todo Escape event attributes like AbstractHtmlElement view helper does in htmlAttribs ??
            try {
                /** @mago-var mixed $escapedAttribute */
                $escapedAttribute = $escapeAttr(value: $value);
                $strings[] = sprintf('%s="%s"', Value::scalar($escape(value: $key)), Value::scalar($escapedAttribute));
            } catch (EscaperException) {
                // If an escaper exception happens, escape only the key, and use a blank value.
                $strings[] = sprintf('%s=""', Value::scalar($escape(value: $key)));
            }
        }

        return implode(separator: ' ', array: Value::scalars($strings));
    }

    /**
     * Get the ID of an element
     *
     * If no ID attribute present, attempts to use the name attribute.
     * If no name attribute is present, either, returns null.
     */
    public function getId(ElementInterface $element): ?string
    {
        $id = $element->getAttribute(key: 'id');
        if (is_string(value: $id) && $id !== '') {
            return $id;
        }

        return $element->getName();
    }

    /**
     * Get the closing bracket for an inline tag
     *
     * Closes as either "/>" for XHTML doctypes or ">" otherwise.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function getInlineClosingBracket(): string
    {
        $doctypeHelper = $this->getDoctypeHelper();
        if ($doctypeHelper->isXhtml()) {
            return '/>';
        }
        return '>';
    }

    /**
     * Retrieve the doctype helper
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function getDoctypeHelper(): Doctype
    {
        if ($this->doctypeHelper) {
            return $this->doctypeHelper;
        }

        if ($this->view !== null) {
            $this->doctypeHelper = $this->getView()->plugin('doctype', \Laminas\View\Helper\Doctype::class);
        }

        if (!$this->doctypeHelper instanceof Doctype) {
            $this->doctypeHelper = new Doctype();
        }

        return $this->doctypeHelper;
    }

    /**
     * Retrieve the escapeHtml helper
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function getEscapeHtmlHelper(): EscapeHtml
    {
        if ($this->escapeHtmlHelper) {
            return $this->escapeHtmlHelper;
        }

        if ($this->view !== null) {
            $this->escapeHtmlHelper = $this->getView()->plugin('escapehtml', \Laminas\View\Helper\EscapeHtml::class);
        }

        if (!$this->escapeHtmlHelper instanceof EscapeHtml) {
            $this->escapeHtmlHelper = new EscapeHtml(new Escaper(Assert::nullOrStringNotEmpty($this->encoding)));
        }

        return $this->escapeHtmlHelper;
    }

    /**
     * Retrieve the escapeHtmlAttr helper
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function getEscapeHtmlAttrHelper(): EscapeHtmlAttr
    {
        if ($this->escapeHtmlAttrHelper) {
            return $this->escapeHtmlAttrHelper;
        }

        if ($this->view !== null) {
            $this->escapeHtmlAttrHelper = $this->getView()->plugin(
                'escapehtmlattr',
                \Laminas\View\Helper\EscapeHtmlAttr::class,
            );
        }

        if (!$this->escapeHtmlAttrHelper instanceof EscapeHtmlAttr) {
            $this->escapeHtmlAttrHelper =
                new EscapeHtmlAttr(new Escaper(Assert::nullOrStringNotEmpty($this->encoding)));
        }

        return $this->escapeHtmlAttrHelper;
    }

    /**
     * Prepare attributes for rendering
     *
     * Ensures appropriate attributes are present (e.g., if "name" is present,
     * but no "id", sets the latter to the former).
     *
     * Removes any invalid attributes
     */
    // Keep the coordinated parsing or rendering steps of this compatibility method together.
    // @mago-expect lint:halstead(1)
    protected function prepareAttributes(array $attributes): array
    {
        /** @mago-var mixed $value */
        foreach ($attributes as $key => $value) {
            $attribute = strtolower(string: (string) $key);

            if (
                ($this->validGlobalAttributes[$attribute] ?? null) === null
                && ($this->validTagAttributes[$attribute] ?? null) === null
                && !$this->hasAllowedPrefix(attribute: $attribute)
            ) {
                unset($attributes[$key]);
                continue;
            }

            // Normalize attribute key, if needed
            if ($attribute !== $key) {
                unset($attributes[$key]);
                $attributes[$attribute] = $value;
            }
            // Normalize boolean attribute values
            if (($this->booleanAttributes[$attribute] ?? null) !== null) {
                $attributes[$attribute] = $this->prepareBooleanAttributeValue(attribute: $attribute, value: $value);
                continue;
            }
            if (!is_string(value: $value)) {
                $attributes[$attribute] = (string) $value;
                continue;
            }
        }

        return $attributes;
    }

    /**
     * Prepare a boolean attribute value
     *
     * Prepares the expected representation for the boolean attribute specified.
     */
    protected function prepareBooleanAttributeValue(string $attribute, mixed $value): string
    {
        if (
            !is_bool(value: $value)
            && in_array(needle: $value, haystack: Assert::isArray($this->booleanAttributes[$attribute]), strict: true)
        ) {
            return Assert::string($value);
        }

        $value = Value::truthy($value);
        return Assert::string(
            $value
                ? Value::accessible($this->booleanAttributes[$attribute])['on']
                : Value::accessible($this->booleanAttributes[$attribute])['off'],
        );
    }

    /**
     * Translates the value of the HTML attribute if it should be translated and this view helper has a translator
     */
    protected function translateHtmlAttributeValue(string $key, ?string $value): ?string
    {
        $translator = $this->getTranslator();
        if (!($value ?? null) || $translator === null) {
            return $value;
        }

        if (
            ($this->translatableAttributes[$key] ?? null) !== null
            || (self::$defaultTranslatableHtmlAttributes[$key] ?? null) !== null
        ) {
            return $translator->translate($value, $this->getTranslatorTextDomain());
        }
        /** @mago-var mixed $prefix */
        foreach ($this->translatableAttributePrefixes as $prefix) {
            if (0 === mb_strpos($key, (string) $prefix)) {
                // prefix matches => return translated $value
                return $translator->translate($value, $this->getTranslatorTextDomain());
            }
        }
        /** @mago-var mixed $prefix */
        foreach (self::$defaultTranslatableHtmlAttributePrefixes as $prefix) {
            if (0 === mb_strpos($key, (string) $prefix)) {
                // default prefix matches => return translated $value
                return $translator->translate($value, $this->getTranslatorTextDomain());
            }
        }

        return $value;
    }

    /**
     * Adds an HTML attribute to the list of valid attributes
     *
     * @throws InvalidArgumentException For attribute names that are invalid per the HTML specifications.
     */
    public function addValidAttribute(string $attribute): static
    {
        if (!$this->isValidAttributeName(attribute: $attribute)) {
            throw new InvalidArgumentException(message: sprintf('%s is not a valid attribute name', $attribute));
        }

        $this->validTagAttributes[$attribute] = true;
        return $this;
    }

    /**
     * Adds a prefix to the list of valid attribute prefixes
     *
     * @throws InvalidArgumentException For attribute prefixes that are invalid
     *                                  per the HTML specifications for attribute names.
     */
    public function addValidAttributePrefix(string $prefix): static
    {
        if (!$this->isValidAttributeName(attribute: $prefix)) {
            throw new InvalidArgumentException(message: sprintf('%s is not a valid attribute prefix', $prefix));
        }

        $this->validTagAttributePrefixes[] = $prefix;
        return $this;
    }

    /**
     * Adds an HTML attribute to the list of translatable attributes
     */
    public function addTranslatableAttribute(string $attribute): static
    {
        $this->translatableAttributes[$attribute] = true;

        return $this;
    }

    /**
     * Adds an HTML attribute to the list of the default translatable attributes
     */
    public static function addDefaultTranslatableAttribute(string $attribute): void
    {
        self::$defaultTranslatableHtmlAttributes[$attribute] = true;
    }

    /**
     * Adds an HTML attribute to the list of translatable attributes
     */
    public function addTranslatableAttributePrefix(string $prefix): static
    {
        $this->translatableAttributePrefixes[] = $prefix;

        return $this;
    }

    /**
     * Adds an HTML attribute to the list of translatable attributes
     */
    public static function addDefaultTranslatableAttributePrefix(string $prefix): void
    {
        self::$defaultTranslatableHtmlAttributePrefixes[] = $prefix;
    }

    /**
     * Whether the passed attribute is valid or not
     *
     * @see https://html.spec.whatwg.org/multipage/syntax.html#attributes-2
     *     Description of valid attributes
     */
    protected function isValidAttributeName(string $attribute): bool
    {
        return (bool) preg_match(pattern: '/^[^\t\n\f \/>"\'=]+$/', subject: $attribute);
    }

    /**
     * Whether the passed attribute has a valid prefix or not
     */
    protected function hasAllowedPrefix(string $attribute): bool
    {
        return array_any($this->validTagAttributePrefixes, static fn($prefix) => str_starts_with(
            haystack: $attribute,
            needle: (string) $prefix,
        ));
    }

    /**
     * Translate the label
     *
     * @internal
     *
     * @todo Reduce argument to only string in the next major
     */
    protected function translateLabel(int|string|float|bool $label): string
    {
        $label = (string) $label;
        return $this->getTranslator()?->translate($label, $this->getTranslatorTextDomain()) ?? $label;
    }

    /**
     * escape the label
     *
     * @internal
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function escapeLabel(ElementInterface $element, string $label): string
    {
        if (
            $element instanceof LabelAwareInterface
            && Value::truthy($element->getLabelOption(key: 'disable_html_escape'))
        ) {
            return $label;
        }

        $escapeHtmlHelper = $this->getEscapeHtmlHelper();
        return Assert::string($escapeHtmlHelper(value: $label));
    }
}
