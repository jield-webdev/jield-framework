<?php

declare(strict_types=1);

namespace Jield\Mvc\ResponseSender;

use Laminas\Http\PhpEnvironment\Response;

class PhpEnvironmentResponseSender extends HttpResponseSender
{
    /**
     * Send php environment response
     *
     * @throws \ReflectionException
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
