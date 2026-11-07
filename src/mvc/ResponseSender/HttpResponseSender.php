<?php

declare(strict_types=1);

namespace Jield\Mvc\ResponseSender;

use Jield\Stdlib\Value;
use Laminas\Http\Response;

class HttpResponseSender extends AbstractResponseSender
{
    /**
     * Send content
     *
     * @return static
     */
    public function sendContent(SendResponseEvent $event): static
    {
        if ($event->contentSent()) {
            return $this;
        }
        $response = $event->getResponse();
        echo Value::stringify($response->getContent());
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
        if (!$response instanceof Response) {
            return;
        }

        $this->sendHeaders($event);
        $this->sendContent($event);
        $event->stopPropagation(true);
    }
}
