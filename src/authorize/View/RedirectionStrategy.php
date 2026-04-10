<?php

declare(strict_types=1);

namespace Jield\Authorize\View;

use Jield\Authorize\Guard\Controller;
use Jield\Authorize\Guard\Route;
use Jield\Authorize\Exception\UnAuthorizedException;
use Laminas\Http\Response;
use Jield\Mvc\Application;
use Jield\Mvc\MvcEvent;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;

/**
 * Dispatch error handler, catches exceptions related with authorization and
 * redirects the user agent to a configured location
 */
class RedirectionStrategy implements ListenerAggregateInterface
{
    /** @var string route to be used to handle redirects */
    protected string $redirectRoute = 'lmcuser/login';

    /** @var ?string URI to be used to handle redirects */
    protected ?string $redirectUri = null;

    /** @var callable[] An array with callback functions or methods. */
    protected array $listeners = [];

    /**
     * {@inheritDoc}
     */
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(eventName: MvcEvent::EVENT_DISPATCH_ERROR, listener: $this->onDispatchError(...), priority: -5000);
    }

    /**
     * {@inheritDoc}
     */
    public function detach(EventManagerInterface $events): void
    {
        foreach ($this->listeners as $index => $listener) {
            if ($events->detach(listener: $listener)) {
                unset($this->listeners[$index]);
            }
        }
    }

    /**
     * Handles redirects in case of dispatch errors caused by unauthorized access
     */
    public function onDispatchError(MvcEvent $event): void
    {
        // Do nothing if the result is a response object
        $result     = $event->getResult();
        $routeMatch = $event->getRouteMatch();
        $response   = $event->getResponse();
        $router     = $event->getRouter();
        $error      = $event->getError();
        $url        = $this->redirectUri;

        if (
            $result instanceof Response
            || !$routeMatch
            || ($response && !$response instanceof Response)
            || !(
                Route::ERROR === $error
                || Controller::ERROR === $error
                || (
                    Application::ERROR_EXCEPTION === $error
                    && $event->getParam(name: 'exception') instanceof UnAuthorizedException
                )
            )
        ) {
            return;
        }

        if (null === $url) {
            $url = $router->assemble(params: [], options: ['name' => $this->redirectRoute]);
        }

        $response = $response ?: new Response();

        $response->getHeaders()->addHeaderLine(headerFieldNameOrLine: 'Location', fieldValue: $url);
        $response->setStatusCode(code: 302);

        $event->setResponse(response: $response);
    }

    /**
     * @param string $redirectRoute
     */
    public function setRedirectRoute(string $redirectRoute): void
    {
        $this->redirectRoute = $redirectRoute;
    }

    /**
     * @param string|null $redirectUri
     */
    public function setRedirectUri(?string $redirectUri): void
    {
        $this->redirectUri = $redirectUri ?: null;
    }
}
