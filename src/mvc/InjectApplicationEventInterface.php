<?php

namespace Jield\Mvc;

use Laminas\EventManager\EventInterface as Event;

interface InjectApplicationEventInterface
{
    /**
     * Compose an Event
     *
     * @return void
     */
    public function setEvent(Event $event): void;

    /**
     * Retrieve the composed event
     *
     * @return Event
     */
    public function getEvent(): Event;
}
