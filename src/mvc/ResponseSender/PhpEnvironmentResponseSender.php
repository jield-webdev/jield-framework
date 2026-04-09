<?php

namespace Jield\Mvc\ResponseSender;

use Jield\Http\PhpEnvironment\Response;

class PhpEnvironmentResponseSender extends HttpResponseSender
{
    /**
     * Send php environment response
     *
     * @return void
     */
    public function __invoke(SendResponseEvent $event): void
    {
        $response = $event->getResponse();
        if (! $response instanceof Response) {
            return $this;
        }

        $this->sendHeaders(event: $event)
             ->sendContent(event: $event);
        $event->stopPropagation(flag: true);
        return $this;
    }
}
