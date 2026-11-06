<?php

declare(strict_types=1);

namespace Jield\ApiTools\ApiProblem\Listener;

use Jield\ApiTools\ApiProblem\ApiProblemResponse;
use Jield\Mvc\ResponseSender\HttpResponseSender;
use Jield\Mvc\ResponseSender\SendResponseEvent;
use Laminas\Http\Response as HttpResponse;
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
    public function sendContent(SendResponseEvent $event): static
    {
        $response = $event->getResponse();
        if (!$response instanceof ApiProblemResponse) {
            return $this;
        }

        $response->getApiProblem()->setDetailIncludesStackTrace(flag: $this->displayExceptions());

        return parent::sendContent(event: $event);
    }

    /**
     * Send HTTP response headers.
     *
     * If an application response is composed, and is an HTTP response, merges
     * its headers with the ApiProblemResponse headers prior to sending them.
     *
     */
    #[Override]
    public function sendHeaders(SendResponseEvent $event): static
    {
        $response = $event->getResponse();
        if (!$response instanceof ApiProblemResponse) {
            return $this;
        }

        $this->mergeHeaders(applicationResponse: $this->applicationResponse, apiProblemResponse: $response);

        return parent::sendHeaders(event: $event);
    }

    /**
     * Send ApiProblem response.
     *
     */
    #[Override]
    public function __invoke(SendResponseEvent $event): void
    {
        $response = $event->getResponse();
        if (!$response instanceof ApiProblemResponse) {
            return;
        }

        $this->sendHeaders(event: $event)->sendContent(event: $event);
        $event->stopPropagation(flag: true);

        return;
    }

    /**
     * Merge headers set on the application response into the API Problem response.
     */
    protected function mergeHeaders(HttpResponse $applicationResponse, ApiProblemResponse $apiProblemResponse): void
    {
        $apiProblemHeaders = $apiProblemResponse->getHeaders();
        foreach ($applicationResponse->getHeaders() as $header) {
            if ($apiProblemHeaders->has($header->getFieldName())) {
                continue;
            }

            $apiProblemHeaders->addHeader(header: \Jield\Stdlib\Value::instance($header, \Laminas\Http\Header\HeaderInterface::class));
        }
    }
}
