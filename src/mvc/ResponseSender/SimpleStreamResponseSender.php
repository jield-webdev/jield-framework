<?php

declare(strict_types=1);

namespace Jield\Mvc\ResponseSender;

use Laminas\Http\Response\Stream;
use Webmozart\Assert\Assert;

use function fpassthru;

class SimpleStreamResponseSender extends AbstractResponseSender
{
    /**
     * Send the stream
     */
    public function sendStream(SendResponseEvent $event): static
    {
        if ($event->contentSent()) {
            return $this;
        }
        $response = Assert::isInstanceOf($event->getResponse(), Stream::class);
        $stream = $response->getStream();
        fpassthru($stream);
        $event->setContentSent();

        return $this;
    }

    /**
     * Send stream response
     *
     * @throws \ReflectionException
     */
    public function __invoke(SendResponseEvent $event): void
    {
        $response = $event->getResponse();
        if (!$response instanceof Stream) {
            return;
        }

        $this->sendHeaders($event);
        $this->sendStream($event);
        $event->stopPropagation(true);
    }
}
