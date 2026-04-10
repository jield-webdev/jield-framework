<?php

declare(strict_types=1);

namespace Jield\ApiTools\ApiProblem\Listener;

use Jield\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Http\Response as HttpResponse;
use Jield\Mvc\ResponseSender\HttpResponseSender;
use Jield\Mvc\ResponseSender\SendResponseEvent;
use Override;

/**
 * Send ApiProblem responses.
 */
class SendApiProblemResponseListener extends HttpResponseSender
{
    /** @var HttpResponse */
    protected HttpResponse $applicationResponse;

    /** @var bool */
    protected bool $displayExceptions = false;

    public function setApplicationResponse(HttpResponse $response): static
    {
        $this->applicationResponse = $response;

        return $this;
    }

    /**
     * Set the flag determining whether exception stack traces are included.
     *
     * @param bool $flag
     * @return static
     */
    public function setDisplayExceptions(bool $flag): static
    {
        $this->displayExceptions = $flag;

        return $this;
    }

    /**
     * Are exception stack traces included in the response?
     *
     * @return bool
     */
    public function displayExceptions(): bool
    {
        return $this->displayExceptions;
    }

    /**
     * Send the response content.
     *
     * Sets the composed ApiProblem's flag for including the stack trace in the
     * detail based on the display exceptions flag, and then sends content.
     *
     */
    #[Override]
    public function sendContent(SendResponseEvent $e): HttpResponseSender
    {
        $response = $e->getResponse();
        if (! $response instanceof ApiProblemResponse) {
            return $this;
        }

        $response->getApiProblem()->setDetailIncludesStackTrace(flag: $this->displayExceptions());

        return parent::sendContent(event: $e);
    }

    /**
     * Send HTTP response headers.
     *
     * If an application response is composed, and is an HTTP response, merges
     * its headers with the ApiProblemResponse headers prior to sending them.
     *
     */
    #[Override]
    public function sendHeaders(SendResponseEvent $e): \Jield\Mvc\ResponseSender\AbstractResponseSender
    {
        $response = $e->getResponse();
        if (! $response instanceof ApiProblemResponse) {
            return $this;
        }

        if ($this->applicationResponse instanceof HttpResponse) {
            $this->mergeHeaders(applicationResponse: $this->applicationResponse, apiProblemResponse: $response);
        }

        return parent::sendHeaders(event: $e);
    }

    /**
     * Send ApiProblem response.
     *
     */
    #[Override]
    public function __invoke(SendResponseEvent $event): void
    {
        $response = $event->getResponse();
        if (! $response instanceof ApiProblemResponse) {
            return $this;
        }

        $this->sendHeaders(e: $event)
             ->sendContent(e: $event);
        $event->stopPropagation(flag: true);

        return $this;
    }

    /**
     * Merge headers set on the application response into the API Problem response.
     */
    protected function mergeHeaders(HttpResponse $applicationResponse, ApiProblemResponse $apiProblemResponse): void
    {
        $apiProblemHeaders = $apiProblemResponse->getHeaders();
        foreach ($applicationResponse->getHeaders() as $header) {
            if ($apiProblemHeaders->has(name: $header->getFieldName())) {
                continue;
            }

            $apiProblemHeaders->addHeader(header: $header);
        }
    }
}
