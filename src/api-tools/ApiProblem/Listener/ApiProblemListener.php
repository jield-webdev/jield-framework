<?php

declare(strict_types=1);

namespace Jield\ApiTools\ApiProblem\Listener;

use Jield\ApiTools\ApiProblem\ApiProblem;
use Jield\ApiTools\ApiProblem\ApiProblemResponse;
use Jield\ApiTools\ApiProblem\View\ApiProblemModel;
use Jield\Mvc\MvcEvent;
use Jield\Stdlib\Value;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Http\Header\Accept as AcceptHeader;
use Laminas\Http\Header\Accept\FieldValuePart\AcceptFieldValuePart;
use Laminas\Http\Headers;
use Laminas\Http\Request as HttpRequest;
use Laminas\Http\Response as HttpResponse;
use Laminas\Stdlib\DispatchableInterface;
use Laminas\View\Model\ModelInterface;
use Override;
use Throwable;
use Webmozart\Assert\Assert;

use function in_array;
use function is_array;
use function is_string;

/**
 * ApiProblemListener.
 *
 * Provides a listener on the render event, at high priority.
 *
 * If the MvcEvent represents an error, then its view model and result are
 * replaced with a RestfulJsonModel containing an API-Problem payload.
 */
class ApiProblemListener extends AbstractListenerAggregate
{
    protected array $acceptFilters = [
        'application/json',
        'application/*+json',
    ];

    /**
     * Set the accept filter, if one is passed
     */
    public function __construct(array|string|null $filters = null)
    {
        if ($filters !== '' && $filters !== '0' && $filters !== []) {
            if (is_string(value: $filters)) {
                $this->acceptFilters = [$filters];
            }

            if (is_array(value: $filters)) {
                $this->acceptFilters = $filters;
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(
            eventName: MvcEvent::EVENT_RENDER,
            listener: $this->onRender(...),
            priority: 1000,
        );
        $this->listeners[] = $events->attach(
            eventName: MvcEvent::EVENT_DISPATCH_ERROR,
            listener: $this->onDispatchError(...),
            priority: 100,
        );

        $sharedEvents = $events->getSharedManager();
        Assert::notNull($sharedEvents)->attach(
            DispatchableInterface::class,
            MvcEvent::EVENT_DISPATCH,
            listener: $this->onDispatch(...),
            priority: 100,
        );
    }

    /**
     * Listen to the render event.
     *
     */
    public function onRender(MvcEvent $e): void
    {
        if (!$this->validateErrorEvent(e: $e)) {
            return;
        }

        // Next, do we have a view model in the result?
        // If not, nothing more to do.
        $model = $e->getResult();
        if (!$model instanceof ModelInterface || $model instanceof ApiProblemModel) {
            return;
        }

        // Marshal the information we need for the API-Problem response
        $response = $e->getResponse();
        $status = $response instanceof HttpResponse ? $response->getStatusCode() : 500;
        $exception = $model->getVariable(name: 'exception');

        if ($exception instanceof Throwable) {
            $apiProblem = new ApiProblem(status: $status, detail: $exception);
        } else {
            $apiProblem = new ApiProblem(
                status: $status,
                detail: Value::stringify($model->getVariable(name: 'message')),
            );
        }

        // Create a new model with the API-Problem payload, and reset
        // the result and view model in the event using it.
        $model = new ApiProblemModel(problem: $apiProblem);
        $e->setResult(result: $model);
        $e->setViewModel(viewModel: $model);
    }

    /**
     * Handle dispatch.
     *
     * It checks if the controller is in our list
     *
     */
    public function onDispatch(MvcEvent $e): void
    {
        $app = $e->getApplication();
        $services = Assert::notNull($app)->getServiceManager();
        $config = $services->get('config');

        if (!isset($config['api-tools-api-problem']['render_error_controllers'])) {
            return;
        }

        $controller = Assert::notNull($e->getRouteMatch())->getParam(name: 'controller');
        $controllers = $config['api-tools-api-problem']['render_error_controllers'];
        if (!in_array(needle: $controller, haystack: $controllers, strict: true)) {
            // The current controller is not in our list of controllers to handle
            return;
        }

        // Attach the ApiProblem render.error listener
        $events = Assert::notNull($app)->getEventManager();
        Value::call($services->get('Jield\ApiTools\ApiProblem\RenderErrorListener'), 'attach', [$events]);
    }

    /**
     * Handle render errors.
     *
     * If the event represents an error, and has an exception composed, marshals an ApiProblem
     * based on the exception, stops event propagation, and returns an ApiProblemResponse.
     *
     */
    public function onDispatchError(MvcEvent $e): ?ApiProblemResponse
    {
        if (!$this->validateErrorEvent(e: $e)) {
            return null;
        }

        // Marshall an ApiProblem and view model based on the exception
        $exception = $e->getParam(name: 'exception');
        if (!$exception instanceof Throwable) {
            // If it's not an exception, do not know what to do.
            return null;
        }

        $e->stopPropagation();
        $response = new ApiProblemResponse(apiProblem: new ApiProblem(
            status: $exception->getCode(),
            detail: $exception,
        ));
        $e->setResponse(response: $response);

        return $response;
    }

    /**
     * Determine if we have a valid error event.
     *
     */
    protected function validateErrorEvent(MvcEvent $e): bool
    {
        // only worried about error pages
        if (!$e->isError()) {
            return false;
        }

        // and then, only if we have an Accept header...
        $request = $e->getRequest();
        if (!$request instanceof HttpRequest) {
            return false;
        }

        $headers = $request->getHeaders();
        if (!Assert::isInstanceOf($headers, Headers::class)->has('Accept')) {
            return false;
        }

        // ... that matches certain criteria
        $accept = Assert::isInstanceOf($headers, Headers::class)->get('Accept');
        return $this->matchAcceptCriteria(accept: Assert::isInstanceOf($accept, \Laminas\Http\Header\Accept::class));
    }

    /**
     * Attempt to match the accept criteria.
     *
     * If it matches, but on "*\/*", return false.
     *
     * Otherwise, return based on whether or not one or more criteria match.
     *
     */
    protected function matchAcceptCriteria(AcceptHeader $accept): bool
    {
        foreach ($this->acceptFilters as $type) {
            $match = $accept->match(matchAgainst: $type);
            if ($match instanceof AcceptFieldValuePart && $match->getTypeString() !== '*/*') {
                return true;
            }
        }

        return false;
    }
}
