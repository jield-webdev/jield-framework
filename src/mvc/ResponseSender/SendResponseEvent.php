<?php

namespace Jield\Mvc\ResponseSender;

use Laminas\EventManager\Event;
use Jield\Stdlib\ResponseInterface;

use function spl_object_hash;

class SendResponseEvent extends Event
{
    /**#@+
     * Send response events triggered by eventmanager
     */
    public const string EVENT_SEND_RESPONSE = 'sendResponse';
    /**#@-*/

    /** @var string Event name */
    protected ?string $name = 'sendResponse';

    /** @var ResponseInterface */
    protected ResponseInterface $response;

    /** @var array */
    protected array $headersSent = [];

    /** @var array */
    protected array $contentSent = [];

    /**
     * @return SendResponseEvent
     */
    public function setResponse(ResponseInterface $response): static
    {
        $this->setParam(name: 'response', value: $response);
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
     * @return SendResponseEvent
     */
    public function setContentSent(): static
    {
        $response                         = $this->getResponse();
        $contentSent                      = $this->getParam(name: 'contentSent', default: []);
        $responseObjectHash               = spl_object_hash(object: $response);
        $contentSent[$responseObjectHash] = true;
        $this->setParam(name: 'contentSent', value: $contentSent);
        $this->contentSent[$responseObjectHash] = true;
        return $this;
    }

    /**
     * @return bool
     */
    public function contentSent(): bool
    {
        $response = $this->getResponse();
        if (isset($this->contentSent[spl_object_hash(object: $response)])) {
            return true;
        }
        return false;
    }

    /**
     * Set headers sent for current response object
     *
     * @return SendResponseEvent
     */
    public function setHeadersSent(): static
    {
        $response                         = $this->getResponse();
        $headersSent                      = $this->getParam(name: 'headersSent', default: []);
        $responseObjectHash               = spl_object_hash(object: $response);
        $headersSent[$responseObjectHash] = true;
        $this->setParam(name: 'headersSent', value: $headersSent);
        $this->headersSent[$responseObjectHash] = true;
        return $this;
    }

    /**
     * @return bool
     */
    public function headersSent(): bool
    {
        $response = $this->getResponse();
        if (isset($this->headersSent[spl_object_hash(object: $response)])) {
            return true;
        }
        return false;
    }
}
