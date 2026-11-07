<?php

declare(strict_types=1);

namespace Jield\Mvc;

use Jield\Mvc\ResponseSender\HttpResponseSender;
use Jield\Mvc\ResponseSender\PhpEnvironmentResponseSender;
use Jield\Mvc\ResponseSender\SendResponseEvent;
use Jield\Mvc\ResponseSender\SimpleStreamResponseSender;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManager;
use Laminas\EventManager\EventManagerAwareInterface;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Stdlib\ResponseInterface as Response;

class SendResponseListener extends AbstractListenerAggregate implements EventManagerAwareInterface
{
    protected ?SendResponseEvent $event = null;

    protected ?EventManagerInterface $eventManager = null;

    /**
     * Inject an EventManager instance
     */
    public function setEventManager(EventManagerInterface $eventManager): void
    {
        $eventManager->setIdentifiers([
            self::class,
            static::class,
        ]);
        $this->eventManager = $eventManager;
        $this->attachDefaultListeners();
    }

    /**
     * Retrieve the event manager
     *
     * Lazy-loads an EventManager instance if none registered.
     */
    public function getEventManager(): EventManagerInterface
    {
        if (!$this->eventManager instanceof EventManagerInterface) {
            $this->setEventManager(new EventManager());
        }
        return $this->eventManager;
    }

    /**
     * Attach the aggregate to the specified event manager
     *
     * @param  int $priority
     */
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_FINISH, $this->sendResponse(...), -10_000);
    }

    /**
     * Send the response
     */
    public function sendResponse(MvcEvent $e): void
    {
        $response = $e->getResponse();

        $event = $this->getEvent();
        $event->setResponse($response);
        $event->setTarget($this);
        $this->getEventManager()->triggerEvent($event);
    }

    /**
     * Get the send response event
     */
    public function getEvent(): SendResponseEvent
    {
        if (!$this->event instanceof SendResponseEvent) {
            $this->setEvent(new SendResponseEvent());
        }
        return $this->event;
    }

    /**
     * Set the send response event
     */
    public function setEvent(SendResponseEvent $e): static
    {
        $this->event = $e;
        return $this;
    }

    /**
     * Register the default event listeners
     *
     * The order in which the response sender are listed here, is by their usage:
     * PhpEnvironmentResponseSender has highest priority, because it's used most often.
     * SimpleStreamResponseSender is not used that often, so has a lower priority.
     * You can attach your response sender before or after every default response sender implementation.
     * All default response sender implementation have negative priority.
     * You are able to attach listeners without giving a priority and your response sender would be first to try.
     */
    protected function attachDefaultListeners(): void
    {
        $events = $this->getEventManager();
        $events->attach(SendResponseEvent::EVENT_SEND_RESPONSE, new PhpEnvironmentResponseSender(), -1000);
        $events->attach(SendResponseEvent::EVENT_SEND_RESPONSE, new SimpleStreamResponseSender(), -3000);
        $events->attach(SendResponseEvent::EVENT_SEND_RESPONSE, new HttpResponseSender(), -4000);
    }
}
