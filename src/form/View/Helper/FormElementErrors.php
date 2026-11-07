<?php

declare(strict_types=1);

namespace Jield\Form\View\Helper;

use Jield\Form\ElementInterface;
use Jield\Form\Exception;

use Jield\Stdlib\Value;
use function array_merge;
use function array_walk_recursive;
use function count;
use function implode;
use function sprintf;

/** @final */
class FormElementErrors extends AbstractHelper
{
    /** @var string Templates for the open/close/separators for message tags */
    protected string $messageOpenFormat = '<ul%s><li>';
    /** @var string Templates for the open/close/separators for message tags */
    protected string $messageCloseString = '</li></ul>';
    /** @var string Templates for the open/close/separators for message tags */
    protected string $messageSeparatorString = '</li><li>';

    /** @var array Default attributes for the open format tag */
    protected array $attributes = [];

    /** @var bool Whether or not to translate error messages during render. */
    protected bool $translateErrorMessages = true;

    /**
     * Invoke helper as functor
     *
     * Proxies to {@link render()} if an element is passed.
     *
     * @template T as null|ElementInterface
     * @psalm-param T $element
     * @psalm-return (T is null ? static : string)
     * @return string|FormElementErrors
     */
    public function __invoke(?ElementInterface $element = null, array $attributes = []): string|static
    {
        if (!$element) {
            return $this;
        }

        return $this->render(element: $element, attributes: $attributes);
    }

    /**
     * Render validation errors for the provided $element
     *
     * If {@link $translateErrorMessages} is true, and a translator is
     * composed, messages retrieved from the element will be translated; if
     * either is not the case, they will not.
     *
     * @throws Exception\DomainException
     */
    public function render(ElementInterface $element, array $attributes = []): string
    {
        $messages = $element->getMessages();
        if (!$messages) {
            return '';
        }

        // Flatten message array
        $messages = $this->flattenMessages(messages: $messages);
        if (!$messages) {
            return '';
        }

        // Prepare attributes for opening tag
        $attributes = array_merge($this->attributes, $attributes);
        $attributes = $this->createAttributesString(attributes: $attributes);
        if (!empty($attributes)) {
            $attributes = ' ' . $attributes;
        }

        $count = count(value: $messages);
        $escaper = $this->getEscapeHtmlHelper();
        for ($i = 0; $i < $count; $i += 1) {
            $messages[$i] = $escaper(value: $messages[$i]);
        }

        // Generate markup
        $markup = sprintf($this->getMessageOpenFormat(), $attributes);
        $markup .= implode(
            separator: $this->getMessageSeparatorString(),
            array: Value::scalars($messages),
        );
        $markup .= $this->getMessageCloseString();

        return $markup;
    }

    /**
     * Set the attributes that will go on the message open format
     *
     * @param  array $attributes key value pairs of attributes
     * @return static
     */
    public function setAttributes(array $attributes): static
    {
        $this->attributes = $attributes;
        return $this;
    }

    /**
     * Get the attributes that will go on the message open format
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Set the string used to close message representation
     *
     * @return static
     */
    public function setMessageCloseString(string $messageCloseString): static
    {
        $this->messageCloseString = $messageCloseString;
        return $this;
    }

    /**
     * Get the string used to close message representation
     */
    public function getMessageCloseString(): string
    {
        return $this->messageCloseString;
    }

    /**
     * Set the formatted string used to open message representation
     *
     * @return static
     */
    public function setMessageOpenFormat(string $messageOpenFormat): static
    {
        $this->messageOpenFormat = $messageOpenFormat;
        return $this;
    }

    /**
     * Get the formatted string used to open message representation
     */
    public function getMessageOpenFormat(): string
    {
        return $this->messageOpenFormat;
    }

    /**
     * Set the string used to separate messages
     *
     * @return static
     */
    public function setMessageSeparatorString(string $messageSeparatorString): static
    {
        $this->messageSeparatorString = $messageSeparatorString;
        return $this;
    }

    /**
     * Get the string used to separate messages
     */
    public function getMessageSeparatorString(): string
    {
        return $this->messageSeparatorString;
    }

    /**
     * Set the flag detailing whether or not to translate error messages.
     *
     * @return static
     */
    public function setTranslateMessages(bool $flag): static
    {
        $this->translateErrorMessages = $flag;
        return $this;
    }

    private function flattenMessages(array $messages): array
    {
        return $this->translateErrorMessages && $this->getTranslator()
            ? $this->flattenMessagesWithTranslator(messages: $messages)
            : $this->flattenMessagesWithoutTranslator(messages: $messages);
    }

    private function flattenMessagesWithoutTranslator(array $messages): array
    {
        $messagesToPrint = [];
        array_walk_recursive(array: $messages, callback: static function (string $item) use (&$messagesToPrint): void {
            $messagesToPrint[] = $item;
        });
        return $messagesToPrint;
    }

    private function flattenMessagesWithTranslator(array $messages): array
    {
        $translator = $this->getTranslator();
        $textDomain = $this->getTranslatorTextDomain();
        $messagesToPrint = [];
        $messageCallback = static function ($item) use (&$messagesToPrint, $translator, $textDomain): void {
            $messagesToPrint[] = Value::present($translator)->translate(
                Value::string($item),
                $textDomain,
            );
        };
        array_walk_recursive(array: $messages, callback: $messageCallback);
        return $messagesToPrint;
    }
}
