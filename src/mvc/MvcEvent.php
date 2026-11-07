<?php

declare(strict_types=1);

namespace Jield\Mvc;

use Jield\Mvc\Router\RouteMatch;
use Jield\Mvc\View\Model\ViewModel;
use Jield\Stdlib\Value;
use Laminas\EventManager\Event;
use Laminas\Router\RouteStackInterface;
use Laminas\Stdlib\RequestInterface as Request;
use Laminas\Stdlib\ResponseInterface as Response;
use Laminas\View\Model\ModelInterface as Model;

/**
 * @extends \Laminas\EventManager\Event<object|string|null, array<string, mixed>>
 */
class MvcEvent extends Event
{
    /**#@+
     * Mvc events triggered by the event manager
     */
    public const string EVENT_BOOTSTRAP = 'bootstrap';
    public const string EVENT_DISPATCH = 'dispatch';
    public const string EVENT_DISPATCH_ERROR = 'dispatch.error';
    public const string EVENT_FINISH = 'finish';
    public const string EVENT_RENDER = 'render';
    public const string EVENT_RENDER_ERROR = 'render.error';
    public const string EVENT_ROUTE = 'route';

    protected ?ApplicationInterface $application = null;

    protected Request $request;

    protected Response $response;

    protected mixed $result = null;

    protected RouteStackInterface $router;

    protected ?RouteMatch $routeMatch = null;

    protected ?Model $viewModel = null;

    /**
     * Set application instance
     */
    public function setApplication(ApplicationInterface $application): static
    {
        $this->setParam('application', $application);
        $this->application = $application;
        return $this;
    }

    /**
     * Get application instance
     */
    public function getApplication(): ?ApplicationInterface
    {
        return $this->application;
    }

    /**
     * Get router
     */
    public function getRouter(): RouteStackInterface
    {
        return $this->router;
    }

    /**
     * Set router
     */
    public function setRouter(RouteStackInterface $router): static
    {
        $this->setParam('router', $router);
        $this->router = $router;
        return $this;
    }

    /**
     * Get route match
     */
    public function getRouteMatch(): ?RouteMatch
    {
        return $this->routeMatch;
    }

    /**
     * Set route match
     */
    public function setRouteMatch(RouteMatch $matches): static
    {
        $this->setParam('route-match', $matches);
        $this->routeMatch = $matches;
        return $this;
    }

    /**
     * Get request
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * Set request
     */
    public function setRequest(Request $request): static
    {
        $this->setParam('request', $request);
        $this->request = $request;
        return $this;
    }

    /**
     * Get response
     */
    public function getResponse(): Response
    {
        return $this->response;
    }

    /**
     * Set response
     */
    public function setResponse(Response $response): static
    {
        $this->setParam('response', $response);
        $this->response = $response;
        return $this;
    }

    /**
     * Set the view model
     */
    public function setViewModel(Model $viewModel): static
    {
        $this->viewModel = $viewModel;
        return $this;
    }

    public function getViewModel(): Model
    {
        $this->viewModel ??= new ViewModel();
        return $this->viewModel;
    }

    /**
     * Get result
     */
    public function getResult(): mixed
    {
        return $this->result;
    }

    /**
     * Set result
     */
    public function setResult(mixed $result): static
    {
        $this->setParam('__RESULT__', $result);
        $this->result = $result;
        return $this;
    }

    /**
     * Does the event represent an error response?
     */
    public function isError(): bool
    {
        return Value::truthy($this->getParam('error', false));
    }

    /**
     * Set the error message (indicating error in handling request)
     */
    public function setError(string $message): static
    {
        $this->setParam('error', $message);
        return $this;
    }

    /**
     * Retrieve the error message, if any
     */
    public function getError(): string
    {
        return \Webmozart\Assert\Assert::string($this->getParam('error', ''));
    }

    /**
     * Get the currently registered controller name
     */
    public function getController(): ?string
    {
        return Value::nullableString($this->getParam('controller'));
    }

    /**
     * Set controller name
     */
    public function setController(string $name): static
    {
        $this->setParam('controller', $name);
        return $this;
    }

    /**
     * Get controller class
     */
    public function getControllerClass(): ?string
    {
        return Value::nullableString($this->getParam('controller-class'));
    }

    /**
     * Set controller class
     */
    public function setControllerClass(string $class): static
    {
        $this->setParam('controller-class', $class);
        return $this;
    }
}
