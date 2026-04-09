<?php

namespace Jield\Mvc\ResponseSender;

interface ResponseSenderInterface
{
    /**
     * Send the response
     *
     * @return void
     */
    public function __invoke(SendResponseEvent $event): void;
}
