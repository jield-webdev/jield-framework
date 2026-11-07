<?php

declare(strict_types=1);

namespace Jield\Mvc\Plugin\FlashMessenger\View\Helper;

/**
 * Flash messenger namespace
 *
 * @internal
 */
class FlashMessengerNamespace
{
    private string $messageCloseString = '</li></ul>';
    private string $messageOpenFormat = '<ul%s><li>';
    private string $messageSeparatorString = '</li><li>';

    public function __construct(
        private readonly string $name,
        private readonly string $classes = '',
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getMessageCloseString(): string
    {
        return $this->messageCloseString;
    }

    public function setMessageCloseString(string $messageCloseString): void
    {
        $this->messageCloseString = $messageCloseString;
    }

    public function getMessageOpenFormat(): string
    {
        return $this->messageOpenFormat;
    }

    public function setMessageOpenFormat(string $messageOpenFormat): self
    {
        $this->messageOpenFormat = $messageOpenFormat;

        return $this;
    }

    public function getMessageSeparatorString(): string
    {
        return $this->messageSeparatorString;
    }

    public function setMessageSeparatorString(string $messageSeparatorString): self
    {
        $this->messageSeparatorString = $messageSeparatorString;

        return $this;
    }

    public function getClasses(): string
    {
        return $this->classes;
    }
}
