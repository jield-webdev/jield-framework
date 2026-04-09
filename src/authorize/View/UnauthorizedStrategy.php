<?php

declare(strict_types=1);

namespace Jield\Authorize\View;

use JetBrains\PhpStorm\Pure;
use Jield\Authentication\AuthenticationService;
use Jield\Authorize\Guard\Controller;
use Jield\Authorize\Guard\Route;
use Jield\Authorize\Exception\UnAuthorizedException;
use Jield\Http\Response as HttpResponse;
use Jield\Mvc\Application;
use Jield\Mvc\MvcEvent;
use Jield\Stdlib\ResponseInterface as Response;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;
use Laminas\Router\Http\TreeRouteStack;
use Laminas\Session\Container;
use Laminas\View\Model\ViewModel;

/**
 * Dispatch error handler, catches exceptions related with authorization and
 * configures the application response accordingly.
 */
class UnauthorizedStrategy implements ListenerAggregateInterface
{
    /** @var string */
    protected string $template;

    /** @var callable[] An array with callback functions or methods. */
    protected array $listeners = [];


    #[Pure]
    public function __construct(private readonly AuthenticationService $authenticationService, private array $config)
    {
    }


    /**
     * {@inheritDoc}
     */
    public function attach(EventManagerInterface $events, int $priority = 1): void
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
     * @param string $template
     */
    public function setTemplate(string $template): void
    {
        $this->template = $template;
    }

    /**
     * @return string
     */
    public function getTemplate(): string
    {
        return $this->template;
    }


    public function onDispatchError(MvcEvent $event): \Jield\Http\PhpEnvironment\Response
    {
        // Do nothing if the result is a response object
        $result = $event->getResult();

        /** @var \Jield\Http\PhpEnvironment\Response $response */
        $response = $event->getResponse();
        if ($result instanceof Response || ($response && !$response instanceof HttpResponse)) {
            return $response;
        }
        // Common view variables
        $viewVariables = [
            'error'    => $event->getParam(name: 'error'),
            'identity' => $event->getParam(name: 'identity'),
        ];
        switch ($event->getError()) {
            case Controller::ERROR:
                $viewVariables['controller'] = $event->getParam(name: 'controller');
                $viewVariables['action']     = $event->getParam(name: 'action');
                break;
            case Route::ERROR:
                $viewVariables['route'] = $event->getParam(name: 'route');
                /*
                 * When the user is not logged in, redirect to the login-page and assemble the referrer
                 */
                if (!$this->authenticationService->hasIdentity()) {
                    $url = $event->getRouter()->assemble(params: [], options: ['name' => $this->config['login_route']]);

                    /** @var TreeRouteStack $router */
                    $router = $event->getRouter();

                    $redirect = $router->assemble(
                        params: $event->getRouteMatch()?->getParams(),
                        options: [
                            'name' => $event->getParam(name: 'route'),
                        ]
                    );

                    //Store the referrer in a session
                    if ($redirect) {
                        //Grab the query and append it to the URL
                        $query   = $router->getRequestUri()->getQuery();
                        $session = new Container('session');
                        /** @phpstan-ignore-next-line */
                        $session->redirect = $redirect . (empty($query) ? '' : '?' . $query);
                    }

                    $response->getHeaders()->addHeaderLine(headerFieldNameOrLine: 'Location', fieldValue: $url);
                    $response->setStatusCode(code: 302);

                    return $response->sendHeaders();
                }
                break;
            case Application::ERROR_EXCEPTION:
                if (!$event->getParam(name: 'exception') instanceof UnAuthorizedException) {
                    return $response;
                }
                $viewVariables['reason'] = $event->getParam(name: 'exception')
                    ->getMessage();
                $viewVariables['error']  = 'error-unauthorized';
                break;
            default:
                return $response;
        }

        $model    = new ViewModel(variables: $viewVariables);
        $response = $response ?: new HttpResponse();
        $model->setTemplate(template: $this->getTemplate());
        $event->getViewModel()->addChild(child: $model);
        $response->setStatusCode(code: 403);
        $event->setResponse(response: $response);

        return $response;
    }
}
