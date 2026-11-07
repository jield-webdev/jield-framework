<?php

declare(strict_types=1);

namespace Jield\Mvc\ResponseSender;

use Jield\Stdlib\Value;
use Laminas\EventManager\Event;
use Laminas\Stdlib\ResponseInterface;

use function spl_object_hash;

/**
 * @extends \Laminas\EventManager\Event<object|string|null, array<string, mixed>>
 */
class SendResponseEvent extends Event
{
    /**#@+
     * Send response events triggered by eventmanager
     */
    public const string EVENT_SEND_RESPONSE = 'sendResponse';

    /** #@- */

    protected $name = 'sendResponse';

    protected ResponseInterface $response;

    protected array $headersSent = [];

    protected array $contentSent = [];

    /**
     * @return static
     */
    public function setResponse(ResponseInterface $response): static
    {
        $this->setParam('response', $response);
        $this->response = $response;
        return $this;
    }

    /**
     * @return ResponseInterface
     */
    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    /**
     * Set content sent for current response
     *
     * @return static
     */
    public function setContentSent(): static
    {
        $response = $this->getResponse();
        /** @var mixed $contentSent */
        $contentSent = $this->getParam('contentSent', []);
        $responseObjectHash = spl_object_hash($response);
        Value::setPath($contentSent, [$responseObjectHash], true);
        $this->setParam('contentSent', $contentSent);
        $this->contentSent[$responseObjectHash] = true;
        return $this;
    }

    /**
     * @return bool
     */
    public function contentSent(): bool
    {
        $response = $this->getResponse();
        if (isset($this->contentSent[spl_object_hash($response)])) {
            return true;
        }
        return false;
    }

    /**
     * Set headers sent for current response object
     *
     * @return static
     */
    public function setHeadersSent(): static
    {
        $response = $this->getResponse();
        /** @var mixed $headersSent */
        $headersSent = $this->getParam('headersSent', []);
        $responseObjectHash = spl_object_hash($response);
        Value::setPath($headersSent, [$responseObjectHash], true);
        $this->setParam('headersSent', $headersSent);
        $this->headersSent[$responseObjectHash] = true;
        return $this;
    }

    /**
     * @return bool
     */
    public function headersSent(): bool
    {
        $response = $this->getResponse();
        if (isset($this->headersSent[spl_object_hash($response)])) {
            return true;
        }
        return false;
    }
}
