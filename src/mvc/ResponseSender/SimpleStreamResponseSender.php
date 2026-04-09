<?php

namespace Jield\Mvc\ResponseSender;

use Jield\Http\Response\Stream;

use function fpassthru;

class SimpleStreamResponseSender extends AbstractResponseSender
{
    /**
     * Send the stream
     *
     * @return SimpleStreamResponseSender
     */
    public function sendStream(SendResponseEvent $event): static
    {
        if ($event->contentSent()) {
            return $this;
        }
        $response = $event->getResponse();
        $stream   = $response->getStream();
        fpassthru(stream: $stream);
        $event->setContentSent();
    }

    /**
     * Send stream response
     *
     * @return void
     */
    public function __invoke(SendResponseEvent $event): void
    {
        $response = $event->getResponse();
        if (! $response instanceof Stream) {
            return $this;
        }

        $this->sendHeaders(event: $event);
        $this->sendStream(event: $event);
        $event->stopPropagation(flag: true);
        return $this;
    }
}
