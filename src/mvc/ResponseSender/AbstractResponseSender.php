<?php

namespace Jield\Mvc\ResponseSender;

use Jield\Http\Header\MultipleHeaderInterface;
use function header;
use function headers_sent;
use function is_iterable;

abstract class AbstractResponseSender implements ResponseSenderInterface
{
    /**
     * Send HTTP headers
     *
     * @return self
     */
    public function sendHeaders(SendResponseEvent $event): self
    {
        if (headers_sent() || $event->headersSent()) {
            return $this;
        }

        $response = $event->getResponse();

        $headers = $response->getHeaders();

        if (is_iterable(value: $headers)) {
            foreach ($response->getHeaders() as $header) {
                if ($header instanceof MultipleHeaderInterface) {
                    header(header: $header->toString(), replace: false);
                    continue;
                }
                header(header: $header->toString());
            }
        }

        $status = $response->renderStatusLine();
        header(header: (string)$status);

        $event->setHeadersSent();
        return $this;
    }
}
