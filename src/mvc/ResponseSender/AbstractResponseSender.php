<?php

declare(strict_types=1);

namespace Jield\Mvc\ResponseSender;

use Jield\Stdlib\Value;
use Laminas\Http\AbstractMessage;
use Laminas\Http\Header\MultipleHeaderInterface;

use Laminas\Http\Response;
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

        $response = Value::instance($event->getResponse(), Response::class);

        /** @var mixed $headers */
        $headers = Value::instance($response, AbstractMessage::class)->getHeaders();

        if (is_iterable($headers)) {
            /** @var mixed $header */
            foreach (Value::instance(
                $response,
                AbstractMessage::class,
            )->getHeaders() as $header) {
                if ($header instanceof MultipleHeaderInterface) {
                    header($header->toString(), false);
                    continue;
                }
                header(Value::string(Value::call($header, 'toString')));
            }
        }

        /** @var mixed $status */
        $status = $response->renderStatusLine();
        header((string) $status);

        $event->setHeadersSent();
        return $this;
    }
}
