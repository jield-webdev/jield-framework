<?php

namespace Jield\Mvc\View\Http;

use Exception;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Jield\Http\Response as HttpResponse;
use Jield\Mvc\Application;
use Jield\Mvc\MvcEvent;
use Jield\Stdlib\ResponseInterface as Response;
use Laminas\View\Model\ViewModel;
use Throwable;

use function is_string;

class RouteNotFoundStrategy extends AbstractListenerAggregate
{
    /**
     * Whether or not to display exceptions related to the 404 condition
     *
     * @var bool
     */
    protected bool $displayExceptions = false;

    /**
     * Whether or not to display the reason for a 404
     *
     * @var bool
     */
    protected bool $displayNotFoundReason = false;

    /**
     * Template to use to report page not found conditions
     *
     * @var string
     */
    protected string $notFoundTemplate = 'error';

    /**
     * The reason for a not-found condition
     *
     * @var false|string
     */
    protected string|false $reason = false;

    /**
     * {@inheritDoc}
     */
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(eventName: MvcEvent::EVENT_DISPATCH, listener: [$this, 'prepareNotFoundViewModel'], priority: -90);
        $this->listeners[] = $events->attach(eventName: MvcEvent::EVENT_DISPATCH_ERROR, listener: [$this, 'detectNotFoundError']);
        $this->listeners[] = $events->attach(eventName: MvcEvent::EVENT_DISPATCH_ERROR, listener: [$this, 'prepareNotFoundViewModel']);
    }

    /**
     * Set value indicating whether or not to display exceptions related to a not-found condition
     *
     * @param bool $displayExceptions
     * @return RouteNotFoundStrategy
     */
    public function setDisplayExceptions(bool $displayExceptions): static
    {
        $this->displayExceptions = $displayExceptions;
        return $this;
    }

    /**
     * Should we display exceptions related to a not-found condition?
     *
     * @return bool
     */
    public function displayExceptions(): bool
    {
        return $this->displayExceptions;
    }

    /**
     * Set value indicating whether or not to display the reason for a not-found condition
     *
     * @param bool $displayNotFoundReason
     * @return RouteNotFoundStrategy
     */
    public function setDisplayNotFoundReason(bool $displayNotFoundReason): static
    {
        $this->displayNotFoundReason = $displayNotFoundReason;
        return $this;
    }

    /**
     * Should we display the reason for a not-found condition?
     *
     * @return bool
     */
    public function displayNotFoundReason(): bool
    {
        return $this->displayNotFoundReason;
    }

    /**
     * Get template for not found conditions
     *
     * @param string $notFoundTemplate
     * @return RouteNotFoundStrategy
     */
    public function setNotFoundTemplate(string $notFoundTemplate): static
    {
        $this->notFoundTemplate = $notFoundTemplate;
        return $this;
    }

    /**
     * Get template for not found conditions
     *
     * @return string
     */
    public function getNotFoundTemplate(): string
    {
        return $this->notFoundTemplate;
    }

    /**
     * Detect if an error is a 404 condition
     *
     * If a "controller not found" or "invalid controller" error type is
     * encountered, sets the response status code to 404.
     *
     * @return void
     */
    public function detectNotFoundError(MvcEvent $e): void
    {
        $error = $e->getError();
        if (empty($error)) {
            return;
        }

        switch ($error) {
            case Application::ERROR_CONTROLLER_NOT_FOUND:
            case Application::ERROR_CONTROLLER_INVALID:
            case Application::ERROR_ROUTER_NO_MATCH:
                $this->reason = $error;
                $response     = $e->getResponse();
                if (! $response) {
                    $response = new HttpResponse();
                    $e->setResponse(response: $response);
                }
                $response->setStatusCode(code: 404);
                break;
            default:
                return;
        }
    }

    /**
     * Create and return a 404 view model
     *
     * @return void
     */
    public function prepareNotFoundViewModel(MvcEvent $e): void
    {
        $vars = $e->getResult();
        if ($vars instanceof Response) {
            // Already have a response as the result
            return;
        }

        $response = $e->getResponse();
        if ($response->getStatusCode() !== 404) {
            // Only handle 404 responses
            return;
        }

        if (! $vars instanceof ViewModel) {
            $model = new ViewModel();
            if (is_string(value: $vars)) {
                $model->setVariable(name: 'message', value: $vars);
            } else {
                $model->setVariable(name: 'message', value: 'Page not found.');
            }
        } else {
            $model = $vars;
            if ($model->getVariable(name: 'message') === null) {
                $model->setVariable(name: 'message', value: 'Page not found.');
            }
        }

        $model->setTemplate(template: $this->getNotFoundTemplate());

        // If displaying reasons, inject the reason
        $this->injectNotFoundReason(model: $model);

        // If displaying exceptions, inject
        $this->injectException(model: $model, e: $e);

        // Inject controller if we're displaying either the reason or the exception
        $this->injectController(model: $model, e: $e);

        $e->setResult(result: $model);
    }

    /**
     * Inject the not-found reason into the model
     *
     * If $displayNotFoundReason is enabled, checks to see if $reason is set,
     * and, if so, injects it into the model. If not, it injects
     * Application::ERROR_CONTROLLER_CANNOT_DISPATCH.
     *
     * @return void
     */
    protected function injectNotFoundReason(ViewModel $model): void
    {
        if (! $this->displayNotFoundReason()) {
            return;
        }

        // no route match, controller not found, or controller invalid
        if ($this->reason) {
            $model->setVariable(name: 'reason', value: $this->reason);
            return;
        }

        // otherwise, must be a case of the controller not being able to
        // dispatch itself.
        $model->setVariable(name: 'reason', value: Application::ERROR_CONTROLLER_CANNOT_DISPATCH);
    }

    /**
     * Inject the exception message into the model
     *
     * If $displayExceptions is enabled, and an exception is found in the
     * event, inject it into the model.
     *
     * @param ViewModel $model
     * @param MvcEvent $e
     * @return void
     */
    protected function injectException(ViewModel $model, MvcEvent $e): void
    {
        if (! $this->displayExceptions()) {
            return;
        }

        $model->setVariable(name: 'display_exceptions', value: true);

        $exception = $e->getParam(name: 'exception', default: false);

        // @TODO clean up once PHP 7 requirement is enforced
        if (! $exception instanceof Exception && ! $exception instanceof Throwable) {
            return;
        }

        $model->setVariable(name: 'exception', value: $exception);
    }

    /**
     * Inject the controller and controller class into the model
     *
     * If either $displayExceptions or $displayNotFoundReason are enabled,
     * injects the controllerClass from the MvcEvent. It checks to see if a
     * controller is present in the MvcEvent, and, if not, grabs it from
     * the route match if present; if a controller is found, it injects it into
     * the model.
     *
     * @param ViewModel $model
     * @param MvcEvent $e
     * @return void
     */
    protected function injectController(ViewModel $model, MvcEvent $e): void
    {
        if (! $this->displayExceptions() && ! $this->displayNotFoundReason()) {
            return;
        }

        $controller = $e->getController();
        if (empty($controller)) {
            $routeMatch = $e->getRouteMatch();
            if (empty($routeMatch)) {
                return;
            }

            $controller = $routeMatch->getParam(name: 'controller', default: false);
            if (! $controller) {
                return;
            }
        }

        $controllerClass = $e->getControllerClass();
        $model->setVariable(name: 'controller', value: $controller);
        $model->setVariable(name: 'controller_class', value: $controllerClass);
    }
}
