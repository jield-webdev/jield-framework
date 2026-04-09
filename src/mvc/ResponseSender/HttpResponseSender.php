<?php

namespace Jield\Mvc\ResponseSender;

use Jield\Http\Response;

class HttpResponseSender extends AbstractResponseSender
{
    /**
     * Send content
     *
     * @return HttpResponseSender
     */
    public function sendContent(SendResponseEvent $event): static
    {
        if ($event->contentSent()) {
            return $this;
        }
        $response = $event->getResponse();
        echo $response->getContent();
        $event->setContentSent();
        return $this;
    }

    /**
     * Send HTTP response
     *
     * @return void
     */
    public function __invoke(SendResponseEvent $event): void
    {
        $response = $event->getResponse();
        if (! $response instanceof Response) {
            return $this;
        }

        $this->sendHeaders($event)
             ->sendContent(event: $event);
        $event->stopPropagation(flag: true);
        return $this;
    }
}
