<?php

declare(strict_types=1);

namespace Jield\Twig\View;

use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;
use Laminas\View\Model\ModelInterface;
use Laminas\View\ViewEvent;

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
        $this->listeners[] = $events->attach(ViewEvent::EVENT_RENDERER, [$this, 'selectRenderer'], $priority);
        $this->listeners[] = $events->attach(ViewEvent::EVENT_RESPONSE, [$this, 'injectResponse'], $priority);
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
     * Determine if the renderer can load the requested template.
     *
     * @return bool|TwigRenderer
     */
    public function selectRenderer(ViewEvent $e)
    {
        $model = $e->getModel();
        if (! $model instanceof ModelInterface) {
            return false;
        }

        $template = $model->getTemplate();
        if ($template === '') {
            return false;
        }

        if ($this->renderer->canRender($template)) {
            return $this->renderer;
        }
        return false;
    }

    /**
     * Inject the response from the renderer.
     */
    public function injectResponse(ViewEvent $e): void
    {
        $renderer = $e->getRenderer();
        if ($renderer !== $this->renderer) {
            return;
        }
        $result   = $e->getResult();
        $response = $e->getResponse();

        if ($response) {
            $response->setContent($result);
        }
    }

    public function setListeners(array $listeners): TwigStrategy
    {
        $this->listeners = $listeners;
        return $this;
    }
}
