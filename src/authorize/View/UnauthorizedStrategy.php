<?php

declare(strict_types=1);

namespace Jield\Authorize\View;

use JetBrains\PhpStorm\Pure;
use Jield\Authorize\Exception\UnAuthorizedException;
use Jield\Authorize\Guard\Controller;
use Jield\Authorize\Guard\Route;
use Jield\Mvc\Application;
use Jield\Mvc\MvcEvent;
use Jield\Mvc\View\Model\ViewModel;
use Jield\Stdlib\Value;
use Laminas\Authentication\AuthenticationService;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;
use Laminas\Http\Request;
use Laminas\Router\Http\TreeRouteStack;
use Laminas\Session\Container;
use Laminas\Stdlib\ResponseInterface as Response;
use Webmozart\Assert\Assert;

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
    public function __construct(
        private readonly AuthenticationService $authenticationService,
        private array $config,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(
            eventName: MvcEvent::EVENT_DISPATCH_ERROR,
            listener: $this->onDispatchError(...),
            priority: -5000,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function detach(EventManagerInterface $events): void
    {
        foreach ($this->listeners as $index => $listener) {
            $events->detach(listener: $listener);

            unset($this->listeners[$index]);
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

    public function onDispatchError(MvcEvent $event): \Laminas\Http\PhpEnvironment\Response
    {
        // Do nothing if the result is a response object
        /** @mago-var mixed $result */
        $result = $event->getResult();

        /** @var \Laminas\Http\PhpEnvironment\Response $response */
        $response = $event->getResponse();
        if ($result instanceof Response) {
            return $response;
        }
        // Common view variables
        $viewVariables = [
            'error' => $event->getParam(name: 'error'),
            'identity' => $event->getParam(name: 'identity'),
        ];
        switch ($event->getError()) {
            case Controller::ERROR:
                $viewVariables['controller'] = $event->getParam(name: 'controller');
                $viewVariables['action'] = $event->getParam(name: 'action');
                break;
            case Route::ERROR:
                $viewVariables['route'] = $event->getParam(name: 'route');
                /*
                 * When the user is not logged in, redirect to the login-page and assemble the referrer
                 */
                if (!$this->authenticationService->hasIdentity()) {
                    $url = $event
                        ->getRouter()
                        ->assemble(params: [], options: ['name' => $this->config['login_route']])
                        ->toString();

                    /** @var TreeRouteStack $router */
                    $router = $event->getRouter();

                    $redirect = $router->assemble(params: Value::routeParams($event->getRouteMatch()?->getParams()), options: [
                        'name' => $event->getParam(name: 'route'),
                    ])->toString();

                    //Store the referrer in a session
                    if ($redirect) {
                        //Grab the query and append it to the URL
                        $query = Assert::isInstanceOf($event->getRequest(), Request::class)->getUri()->getQuery();
                        $session = new Container('session');
                        $session['redirect'] = $redirect . (empty($query) ? '' : '?' . Value::stringify($query));
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
                $viewVariables['reason'] = Value::call($event->getParam(name: 'exception'), 'getMessage');
                $viewVariables['error'] = 'error-unauthorized';
                break;
            default:
                return $response;
        }

        $model = new ViewModel(variables: $viewVariables);

        $model->setTemplate(template: Assert::stringNotEmpty($this->getTemplate()));
        $event->getViewModel()->addChild(child: $model);
        $response->setStatusCode(code: 403);
        $event->setResponse(response: $response);

        return $response;
    }
}
