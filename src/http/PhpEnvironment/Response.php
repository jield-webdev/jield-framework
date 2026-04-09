<?php

namespace Jield\Http\PhpEnvironment;

use Jield\Http\Header\HeaderInterface;
use Jield\Http\Header\MultipleHeaderInterface;
use Jield\Http\Response as HttpResponse;

use function call_user_func;
use function header;

/**
 * HTTP Response for current PHP environment
 */
class Response extends HttpResponse
{
    /**
     * @deprecated This property is deprecated, and will be removed
     *
     * @var bool
     */
    public bool $headersSent;

    /**
     * The current used version
     * (The value will be detected on getVersion)
     *
     * @var null|string
     */
    protected string $version;

    /** @var bool */
    protected bool $contentSent = false;

    /** @var null|callable */
    private $headersSentHandler;

    /**
     * Return the HTTP version for this response
     *
     * @see \Jield\Http\AbstractMessage::getVersion()
     *
     * @return string
     */
    public function getVersion(): string
    {
        if (! $this->version) {
            $this->version = $this->detectVersion();
        }
        return $this->version;
    }

    /**
     * Detect the current used protocol version.
     * If detection failed it falls back to version 1.0.
     *
     * @return string
     */
    protected function detectVersion(): string
    {
        if (isset($_SERVER['SERVER_PROTOCOL']) && $_SERVER['SERVER_PROTOCOL'] === 'HTTP/1.1') {
            return self::VERSION_11;
        }

        return self::VERSION_10;
    }

    /**
     * @return bool
     */
    public function headersSent(): bool
    {
        return headers_sent();
    }

    /**
     * @return bool
     */
    public function contentSent(): bool
    {
        return $this->contentSent;
    }

    /**
     * @return void
     */
    public function setHeadersSentHandler(callable $handler): void
    {
        $this->headersSentHandler = $handler;
    }

    /**
     * Send HTTP headers
     *
     * @return $this
     */
    public function sendHeaders(): static
    {
        if ($this->headersSent()) {
            if ($this->headersSentHandler) {
                call_user_func($this->headersSentHandler, $this);
            }

            return $this;
        }

        $status = $this->renderStatusLine();
        header(header: $status);

        /** @var HeaderInterface $header */
        foreach ($this->getHeaders() as $header) {
            if ($header instanceof MultipleHeaderInterface) {
                header(header: $header->toString(), replace: false);
                continue;
            }
            header(header: $header->toString());
        }

        $this->headersSent = true;
        return $this;
    }

    /**
     * Send content
     *
     * @return $this
     */
    public function sendContent(): static
    {
        if ($this->contentSent()) {
            return $this;
        }

        echo $this->getContent();
        $this->contentSent = true;
        return $this;
    }

    /**
     * Send HTTP response
     *
     * @return $this
     */
    public function send(): static
    {
        $this->sendHeaders()
             ->sendContent();
        return $this;
    }
}
