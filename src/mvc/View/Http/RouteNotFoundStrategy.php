<?php

declare(strict_types=1);

namespace Jield\Mvc\View\Http;

use Exception;
use Jield\Mvc\Application;
use Jield\Mvc\MvcEvent;
use Jield\Mvc\View\Model\ViewModel;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Stdlib\ResponseInterface as Response;
use Throwable;
use Webmozart\Assert\Assert;

use function is_string;

// Preserve the existing public API and the state and operations that implement it.
// @mago-expect lint:cyclomatic-complexity(1)
class RouteNotFoundStrategy extends AbstractListenerAggregate
{
    /**
     * Whether or not to display exceptions related to the 404 condition
     */
    protected bool $displayExceptions = false;

    /**
     * Whether or not to display the reason for a 404
     */
    protected bool $displayNotFoundReason = false;

    /**
     * Template to use to report page not found conditions
     */
    protected string $notFoundTemplate = 'error';

    /**
     * The reason for a not-found condition
     */
    protected string|false $reason = false;

    /**
     * {@inheritDoc}
     */
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_DISPATCH, $this->prepareNotFoundViewModel(...), -90);
        $this->listeners[] = $events->attach(MvcEvent::EVENT_DISPATCH_ERROR, $this->detectNotFoundError(...));
        $this->listeners[] = $events->attach(MvcEvent::EVENT_DISPATCH_ERROR, $this->prepareNotFoundViewModel(...));
    }

    /**
     * Set value indicating whether or not to display exceptions related to a not-found condition
     *
     * @param  bool $displayExceptions
     */
    public function setDisplayExceptions($displayExceptions): static
    {
        $this->displayExceptions = $displayExceptions;
        return $this;
    }

    /**
     * Should we display exceptions related to a not-found condition?
     */
    public function displayExceptions(): bool
    {
        return $this->displayExceptions;
    }

    /**
     * Set value indicating whether or not to display the reason for a not-found condition
     *
     * @param  bool $displayNotFoundReason
     */
    public function setDisplayNotFoundReason($displayNotFoundReason): static
    {
        $this->displayNotFoundReason = $displayNotFoundReason;
        return $this;
    }

    /**
     * Should we display the reason for a not-found condition?
     */
    public function displayNotFoundReason(): bool
    {
        return $this->displayNotFoundReason;
    }

    /**
     * Get template for not found conditions
     *
     * @param  string $notFoundTemplate
     */
    public function setNotFoundTemplate($notFoundTemplate): static
    {
        $this->notFoundTemplate = $notFoundTemplate;
        return $this;
    }

    /**
     * Get template for not found conditions
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
     */
    public function detectNotFoundError(MvcEvent $e): void
    {
        $error = $e->getError();
        if (!$error) {
            return;
        }

        switch ($error) {
            case Application::ERROR_CONTROLLER_NOT_FOUND:
            case Application::ERROR_CONTROLLER_INVALID:
            case Application::ERROR_ROUTER_NO_MATCH:
                $this->reason = $error;
                $response = $e->getResponse();

                Assert::isInstanceOf($response, \Laminas\Http\Response::class)->setStatusCode(404);
                break;
            default:
                return;
        }
    }

    /**
     * Create and return a 404 view model
     */
    public function prepareNotFoundViewModel(MvcEvent $e): void
    {
        /** @mago-var mixed $vars */
        $vars = $e->getResult();
        if ($vars instanceof Response) {
            // Already have a response as the result
            return;
        }

        $response = $e->getResponse();
        if (Assert::isInstanceOf($response, \Laminas\Http\Response::class)->getStatusCode() !== 404) {
            // Only handle 404 responses
            return;
        }

        // These alternatives are mutually exclusive; the following code consumes the selected state.
        // @mago-expect lint:no-else-clause(2)
        if (!$vars instanceof ViewModel) {
            $model = new ViewModel();
            if (is_string($vars)) {
                $model->setVariable('message', $vars);
            } else {
                $model->setVariable('message', 'Page not found.');
            }
        } else {
            $model = $vars;
            if ($model->getVariable('message') === null) {
                $model->setVariable('message', 'Page not found.');
            }
        }

        $model->setTemplate(Assert::stringNotEmpty($this->getNotFoundTemplate()));

        // If displaying reasons, inject the reason
        $this->injectNotFoundReason($model);

        // If displaying exceptions, inject
        $this->injectException($model, $e);

        // Inject controller if we're displaying either the reason or the exception
        $this->injectController($model, $e);

        $e->setResult($model);
    }

    /**
     * Inject the not-found reason into the model
     *
     * If $displayNotFoundReason is enabled, checks to see if $reason is set,
     * and, if so, injects it into the model. If not, it injects
     * Application::ERROR_CONTROLLER_CANNOT_DISPATCH.
     */
    protected function injectNotFoundReason(ViewModel $model): void
    {
        if (!$this->displayNotFoundReason()) {
            return;
        }

        // no route match, controller not found, or controller invalid
        if ($this->reason) {
            $model->setVariable('reason', $this->reason);
            return;
        }

        // otherwise, must be a case of the controller not being able to
        // dispatch itself.
        $model->setVariable('reason', Application::ERROR_CONTROLLER_CANNOT_DISPATCH);
    }

    /**
     * Inject the exception message into the model
     *
     * If $displayExceptions is enabled, and an exception is found in the
     * event, inject it into the model.
     *
     * @param  ViewModel $model
     * @param  MvcEvent $e
     */
    protected function injectException($model, $e): void
    {
        if (!$this->displayExceptions()) {
            return;
        }

        $model->setVariable('display_exceptions', true);

        /** @mago-var mixed $exception */
        $exception = $e->getParam('exception', false);

        // @TODO clean up once PHP 7 requirement is enforced
        if (!$exception instanceof Exception && !$exception instanceof Throwable) {
            return;
        }

        $model->setVariable('exception', $exception);
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
     * @param  ViewModel $model
     * @param  MvcEvent $e
     */
    protected function injectController($model, $e): void
    {
        if (!$this->displayExceptions() && !$this->displayNotFoundReason()) {
            return;
        }

        $controller = $e->getController();
        if (!($controller ?? null)) {
            $routeMatch = $e->getRouteMatch();
            if (!($routeMatch ?? null)) {
                return;
            }

            /** @mago-var mixed $controller */
            $controller = $routeMatch->getParam('controller', false);
            if (!$controller) {
                return;
            }
        }

        $controllerClass = $e->getControllerClass();
        $model->setVariable('controller', $controller);
        $model->setVariable('controller_class', $controllerClass);
    }
}
