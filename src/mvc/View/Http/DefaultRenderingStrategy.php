<?php

declare(strict_types=1);

namespace Jield\Mvc\View\Http;

use Exception;
use Jield\Mvc\Application;
use Jield\Mvc\MvcEvent;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Stdlib\ResponseInterface as Response;
use Laminas\View\Model\ModelInterface as ViewModel;
use Laminas\View\View;
use Throwable;
use Webmozart\Assert\Assert;

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
    public function __construct(
        protected View $view,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_RENDER, $this->render(...), -10_000);
        $this->listeners[] = $events->attach(MvcEvent::EVENT_RENDER_ERROR, $this->render(...), -10_000);
    }

    /**
     * Set layout template value
     *
     * @param string $layoutTemplate
     * @return static
     */
    public function setLayoutTemplate($layoutTemplate): static
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
        $request = $e->getRequest();
        $response = $e->getResponse();
        $viewModel = $e->getViewModel();

        $view = $this->view;

        $caughtException = null;

        try {
            $response->setContent($view->renderLayout($viewModel));
        } catch (Throwable $ex) {
            $caughtException = $ex;
        }

        if ($caughtException !== null) {
            if ($e->getName() === MvcEvent::EVENT_RENDER_ERROR) {
                throw $caughtException;
            }

            $application = $e->getApplication();
            $events = Assert::notNull($application)->getEventManager();

            $e->setError(Application::ERROR_EXCEPTION);
            $e->setParam('exception', $caughtException);
            $e->setName(MvcEvent::EVENT_RENDER_ERROR);
            $events->triggerEvent($e);
        }

        return $response;
    }
}
