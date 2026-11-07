<?php

declare(strict_types=1);

namespace Jield\Mvc\ResponseSender;

interface ResponseSenderInterface
{
    /**
     * Send the response
     */
    public function __invoke(SendResponseEvent $event): void;
}
