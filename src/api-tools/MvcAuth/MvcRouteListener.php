<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth;

use Jield\Authentication\AuthenticationService;
use Jield\Authentication\Result;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Jield\Http\Request as HttpRequest;
use Jield\Mvc\MvcEvent;
use Jield\Stdlib\ResponseInterface as Response;

use Override;
use function is_bool;

class MvcRouteListener extends AbstractListenerAggregate
{
    /** @var AuthenticationService */
    protected AuthenticationService $authentication;

    /** @var EventManagerInterface */
    protected EventManagerInterface $events;

    /** @var MvcAuthEvent */
    protected MvcAuthEvent $mvcAuthEvent;

    public function __construct(
        MvcAuthEvent $mvcAuthEvent,
        EventManagerInterface $events,
        AuthenticationService $authentication
    ) {
        $this->attach(events: $events);
        $mvcAuthEvent->setTarget(target: $this);
        $this->mvcAuthEvent   = $mvcAuthEvent;

        $this->events         = $events;
        $this->authentication = $authentication;
    }

    /**
     * Attach listeners
     *
     * @param int $priority
     */
    #[Override]
    public function attach(EventManagerInterface $events, int $priority = 1): void
    {
        $this->listeners[] = $events->attach(eventName: MvcEvent::EVENT_ROUTE, listener: $this->authentication(...), priority: -50);
        $this->listeners[] = $events->attach(eventName: MvcEvent::EVENT_ROUTE, listener: $this->authenticationPost(...), priority: -51);
        $this->listeners[] = $events->attach(eventName: MvcEvent::EVENT_ROUTE, listener: $this->authorization(...), priority: -600);
        $this->listeners[] = $events->attach(eventName: MvcEvent::EVENT_ROUTE, listener: $this->authorizationPost(...), priority: -601);
    }

    /**
     * Trigger the authentication event
     *
     */
    public function authentication(MvcEvent $mvcEvent): ?Response
    {
        if (
            ! $mvcEvent->getRequest() instanceof HttpRequest
            || $mvcEvent->getRequest()->isOptions()
        ) {
            return null;
        }

        $mvcAuthEvent = $this->mvcAuthEvent;
        $mvcAuthEvent->setName(name: $mvcAuthEvent::EVENT_AUTHENTICATION);

        $responses = $this->events->triggerEventUntil(callback: fn($r) => $r instanceof Identity\IdentityInterface
            || $r instanceof Result
            || $r instanceof Response, event: $mvcAuthEvent);

        $result  = $responses->last();
        $storage = $this->authentication->getStorage();

        // If we have a response, return immediately
        if ($result instanceof Response) {
            return $result;
        }

        // Determine if the listener returned an identity
        if ($result instanceof Identity\IdentityInterface) {
            $storage->write(contents: $result);
        }

        // If we have a Result, we create an AuthenticatedIdentity from it
        if (
            $result instanceof Result
            && $result->isValid()
        ) {
            $mvcAuthEvent->setAuthenticationResult(result: $result);
            $mvcAuthEvent->setIdentity(identity: new Identity\AuthenticatedIdentity(identity: $result->getIdentity()));
            return null;
        }

        $identity = $this->authentication->getIdentity();
        if ($identity === null && ! $mvcAuthEvent->hasAuthenticationResult()) {
            // if there is no Authentication identity or result, safe to assume we have a guest
            $mvcAuthEvent->setIdentity(identity: new Identity\GuestIdentity());
            return null;
        }

        if (
            $mvcAuthEvent->hasAuthenticationResult()
            && $mvcAuthEvent->getAuthenticationResult()->isValid()
        ) {
            $mvcAuthEvent->setIdentity(
                identity: new Identity\AuthenticatedIdentity(
                    identity: $mvcAuthEvent->getAuthenticationResult()->getIdentity()
                )
            );
        }

        if ($identity instanceof Identity\IdentityInterface) {
            $mvcAuthEvent->setIdentity(identity: $identity);
            return null;
        }

        if ($identity !== null) {
            // identity found in authentication; we can assume we're authenticated
            $mvcAuthEvent->setIdentity(identity: new Identity\AuthenticatedIdentity(identity: $identity));
            return null;
        }

        return null;
    }

    /**
     * Trigger the authentication.post event
     *
     * @return Response|mixed
     */
    public function authenticationPost(MvcEvent $mvcEvent): mixed
    {
        if (
            ! $mvcEvent->getRequest() instanceof HttpRequest
            || $mvcEvent->getRequest()->isOptions()
        ) {
            return null;
        }

        $mvcAuthEvent = $this->mvcAuthEvent;
        $mvcAuthEvent->setName(name: $mvcAuthEvent::EVENT_AUTHENTICATION_POST);

        $responses = $this->events->triggerEventUntil(callback: fn($r) => $r instanceof Response, event: $mvcAuthEvent);

        return $responses->last();
    }

    /**
     * Trigger the authorization event
     *
     */
    public function authorization(MvcEvent $mvcEvent): ?Response
    {
        if (
            ! $mvcEvent->getRequest() instanceof HttpRequest
            || $mvcEvent->getRequest()->isOptions()
        ) {
            return null;
        }

        $mvcAuthEvent = $this->mvcAuthEvent;
        $mvcAuthEvent->setName(name: $mvcAuthEvent::EVENT_AUTHORIZATION);

        $responses = $this->events->triggerEventUntil(callback: fn($r) => is_bool(value: $r) || $r instanceof Response, event: $mvcAuthEvent);

        $result = $responses->last();

        if (is_bool(value: $result)) {
            $mvcAuthEvent->setIsAuthorized(flag: $result);
            return null;
        }

        if ($result instanceof Response) {
            return $result;
        }

        return null;
    }

    /**
     * Trigger the authorization.post event
     *
     */
    public function authorizationPost(MvcEvent $mvcEvent): ?Response
    {
        if (
            ! $mvcEvent->getRequest() instanceof HttpRequest
            || $mvcEvent->getRequest()->isOptions()
        ) {
            return null;
        }

        $mvcAuthEvent = $this->mvcAuthEvent;
        $mvcAuthEvent->setName(name: $mvcAuthEvent::EVENT_AUTHORIZATION_POST);

        $responses = $this->events->triggerEventUntil(callback: fn($r) => $r instanceof Response, event: $mvcAuthEvent);

        return $responses->last();
    }
}
