<?php

declare(strict_types=1);

namespace Jield\Twig\View;

use Jield\Mvc\MvcEvent;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;

class TwigStrategy implements ListenerAggregateInterface
{
    /** @var callable[] */
    protected array $listeners = [];


    public function __construct(protected TwigRenderer $renderer)
    {
    }

    /**
     * Attach one or more listeners
     *
     * Implementors may add an optional $priority argument; the EventManager
     * implementation will pass this to the aggregate.
     *
     * @param int $priority
     */
    public function attach(EventManagerInterface $events, $priority = 100): void
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_RENDER, [$this, 'render'], $priority);
        $this->listeners[] = $events->attach(MvcEvent::EVENT_RENDER_ERROR, [$this, 'render'], $priority);
    }

    /**
     * Detach all previously attached listeners
     */
    public function detach(EventManagerInterface $events): void
    {
        foreach ($this->listeners as $index => $listener) {
            $events->detach($listener);
            unset($this->listeners[$index]);
        }
    }

    /**
     * Render the view model when the template is a Twig template.
     */
    public function render(MvcEvent $e): void
    {
        $model    = $e->getViewModel();
        $template = $model->getTemplate();

        if ($template === '') {
            return;
        }

        if (!$this->renderer->canRender($template)) {
            return;
        }

        $content  = $this->renderer->render($model);
        $response = $e->getResponse();
        if ($response) {
            $response->setContent($content);
            $e->setResult($response);
        } else {
            $e->setResult($content);
        }

        $e->stopPropagation();
    }

    public function setListeners(array $listeners): TwigStrategy
    {
        $this->listeners = $listeners;
        return $this;
    }
}
