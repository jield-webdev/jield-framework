<?php

declare(strict_types=1);

namespace Jield\Mvc;

use Laminas\EventManager\EventInterface as Event;

interface InjectApplicationEventInterface
{
    /**
     * Compose an Event
     */
    public function setEvent(Event $event): void;

    /**
     * Retrieve the composed event
     */
    public function getEvent(): Event;
}
