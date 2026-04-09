<?php

namespace Jield\Mvc\View\Http;

use Exception;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Jield\Mvc\Application;
use Jield\Mvc\MvcEvent;
use Jield\Stdlib\ResponseInterface as Response;
use Laminas\View\Model\ModelInterface as ViewModel;
use Laminas\View\View;
use Throwable;

class DefaultRenderingStrategy extends AbstractListenerAggregate
{
    /**
     * Layout template - template used in root ViewModel of MVC event.
     *
     * @var string
     */
    protected string $layoutTemplate = 'layout';

    /**
     * Set view
     */
    public function __construct(protected View $view)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(eventName: MvcEvent::EVENT_RENDER, listener: [$this, 'render'], priority: -10000);
        $this->listeners[] = $events->attach(eventName: MvcEvent::EVENT_RENDER_ERROR, listener: [$this, 'render'], priority: -10000);
    }

    /**
     * Set layout template value
     *
     * @param string $layoutTemplate
     * @return DefaultRenderingStrategy
     */
    public function setLayoutTemplate(string $layoutTemplate): static
    {
        $this->layoutTemplate = $layoutTemplate;
        return $this;
    }

    /**
     * Get layout template value
     *
     * @return string
     */
    public function getLayoutTemplate(): string
    {
        return $this->layoutTemplate;
    }

    /**
     * Render the view
     *
     * @return Response|null
     * @throws Exception|Throwable
     */
    public function render(MvcEvent $e): ?Response
    {
        $result = $e->getResult();
        if ($result instanceof Response) {
            return $result;
        }

        // Martial arguments
        $request   = $e->getRequest();
        $response  = $e->getResponse();
        $viewModel = $e->getViewModel();
        if (! $viewModel instanceof ViewModel) {
            return;
        }

        $view = $this->view;
        $view->setRequest($request);
        $view->setResponse($response);

        $caughtException = null;

        try {
            $view->render(modelOrTemplate: $viewModel);
        } catch (Throwable $ex) {
            $caughtException = $ex;
        }

        if ($caughtException !== null) {
            if ($e->getName() === MvcEvent::EVENT_RENDER_ERROR) {
                throw $caughtException;
            }

            $application = $e->getApplication();
            $events      = $application->getEventManager();

            $e->setError(message: Application::ERROR_EXCEPTION);
            $e->setParam(name: 'exception', value: $caughtException);
            $e->setName(name: MvcEvent::EVENT_RENDER_ERROR);
            $events->triggerEvent(event: $e);
        }

        return $response;
    }
}
