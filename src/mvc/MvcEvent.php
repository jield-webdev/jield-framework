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
     *
     * @return static
     */
    public function setApplication(ApplicationInterface $application): static
    {
        $this->setParam('application', $application);
        $this->application = $application;
        return $this;
    }

    /**
     * Get application instance
     *
     * @return ApplicationInterface
     */
    public function getApplication(): ?ApplicationInterface
    {
        return $this->application;
    }

    /**
     * Get router
     *
     * @return RouteStackInterface
     */
    public function getRouter(): RouteStackInterface
    {
        return $this->router;
    }

    /**
     * Set router
     *
     * @return static
     */
    public function setRouter(RouteStackInterface $router): static
    {
        $this->setParam('router', $router);
        $this->router = $router;
        return $this;
    }

    /**
     * Get route match
     *
     * @return null|RouteMatch
     */
    public function getRouteMatch(): ?RouteMatch
    {
        return $this->routeMatch;
    }

    /**
     * Set route match
     *
     * @return static
     */
    public function setRouteMatch(RouteMatch $matches): static
    {
        $this->setParam('route-match', $matches);
        $this->routeMatch = $matches;
        return $this;
    }

    /**
     * Get request
     *
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * Set request
     *
     * @return static
     */
    public function setRequest(Request $request): static
    {
        $this->setParam('request', $request);
        $this->request = $request;
        return $this;
    }

    /**
     * Get response
     *
     * @return Response
     */
    public function getResponse(): Response
    {
        return $this->response;
    }

    /**
     * Set response
     *
     * @return static
     */
    public function setResponse(Response $response): static
    {
        $this->setParam('response', $response);
        $this->response = $response;
        return $this;
    }

    /**
     * Set the view model
     *
     * @return static
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
     *
     * @return static
     */
    public function setResult(mixed $result): static
    {
        $this->setParam('__RESULT__', $result);
        $this->result = $result;
        return $this;
    }

    /**
     * Does the event represent an error response?
     *
     * @return bool
     */
    public function isError(): bool
    {
        return Value::truthy($this->getParam('error', false));
    }

    /**
     * Set the error message (indicating error in handling request)
     *
     * @param string $message
     * @return static
     */
    public function setError(string $message): static
    {
        $this->setParam('error', $message);
        return $this;
    }

    /**
     * Retrieve the error message, if any
     *
     * @return string
     */
    public function getError(): string
    {
        return \Webmozart\Assert\Assert::string($this->getParam('error', ''));
    }

    /**
     * Get the currently registered controller name
     *
     * @return string
     */
    public function getController(): ?string
    {
        return Value::nullableString($this->getParam('controller'));
    }

    /**
     * Set controller name
     *
     * @param string $name
     * @return static
     */
    public function setController(string $name): static
    {
        $this->setParam('controller', $name);
        return $this;
    }

    /**
     * Get controller class
     *
     * @return string
     */
    public function getControllerClass(): ?string
    {
        return Value::nullableString($this->getParam('controller-class'));
    }

    /**
     * Set controller class
     *
     * @param string $class
     * @return static
     */
    public function setControllerClass(string $class): static
    {
        $this->setParam('controller-class', $class);
        return $this;
    }
}
