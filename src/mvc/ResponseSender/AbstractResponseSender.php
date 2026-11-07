<?php

declare(strict_types=1);

namespace Jield\Mvc\ResponseSender;

use Jield\Stdlib\Value;
use Laminas\Http\Header\MultipleHeaderInterface;
use Laminas\Http\Response;
use Webmozart\Assert\Assert;

use function header;
use function headers_sent;

abstract class AbstractResponseSender implements ResponseSenderInterface
{
    /**
     * Send HTTP headers
     *
     * @throws \ReflectionException
     */
    public function sendHeaders(SendResponseEvent $event): static
    {
        if (headers_sent() || $event->headersSent()) {
            return $this;
        }

        $response = Assert::isInstanceOf($event->getResponse(), Response::class);

        $headers = $response->getHeaders();

        foreach ($headers as $header) {
            if ($header instanceof MultipleHeaderInterface) {
                header($header->toString(), false);
                continue;
            }
            header(Assert::string(Value::call($header, 'toString')));
        }

        $status = $response->renderStatusLine();
        header($status);

        $event->setHeadersSent();
        return $this;
    }
}
