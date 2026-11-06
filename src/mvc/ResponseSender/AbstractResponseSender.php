<?php

declare(strict_types=1);

namespace Jield\Mvc\ResponseSender;

use Laminas\Http\Header\MultipleHeaderInterface;

use function header;
use function headers_sent;
use function is_iterable;

abstract class AbstractResponseSender implements ResponseSenderInterface
{
    /**
     * Send HTTP headers
     *
     * @return static
     */
    public function sendHeaders(SendResponseEvent $event): static
    {
        if (headers_sent() || $event->headersSent()) {
            return $this;
        }

        $response = \Jield\Stdlib\Value::instance($event->getResponse(), \Laminas\Http\Response::class);

        /** @var mixed $headers */
        $headers = \Jield\Stdlib\Value::instance($response, \Laminas\Http\AbstractMessage::class)->getHeaders();

        if (is_iterable($headers)) {
            /** @var mixed $header */
            foreach (\Jield\Stdlib\Value::instance($response, \Laminas\Http\AbstractMessage::class)->getHeaders() as $header) {
                if ($header instanceof MultipleHeaderInterface) {
                    header($header->toString(), false);
                    continue;
                }
                header(\Jield\Stdlib\Value::string(\Jield\Stdlib\Value::call($header, 'toString')));
            }
        }

        /** @var mixed $status */
        $status = $response->renderStatusLine();
        header((string) $status);

        $event->setHeadersSent();
        return $this;
    }
}
