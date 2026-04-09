<?php

namespace Jield\Mvc\View\Http;

use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface as Events;
use Jield\Mvc\MvcEvent;
use Laminas\View\Model\ClearableModelInterface;
use Laminas\View\Model\ModelInterface as ViewModel;

class InjectViewModelListener extends AbstractListenerAggregate
{
    /**
     * {@inheritDoc}
     */
    public function attach(Events $events, int $priority = 1): void
    {
        $this->listeners[] = $events->attach(eventName: MvcEvent::EVENT_DISPATCH, listener: [$this, 'injectViewModel'], priority: -100);
        $this->listeners[] = $events->attach(eventName: MvcEvent::EVENT_DISPATCH_ERROR, listener: [$this, 'injectViewModel'], priority: -100);
        $this->listeners[] = $events->attach(eventName: MvcEvent::EVENT_RENDER_ERROR, listener: [$this, 'injectViewModel'], priority: -100);
    }

    /**
     * Insert the view model into the event
     *
     * Inspects the MVC result; if it's a view model, it then either (a) adds
     * it as a child to the default, composed view model, or (b) replaces it
     * if the result is marked as terminable.
     *
     * @return void
     */
    public function injectViewModel(MvcEvent $e): void
    {
        $result = $e->getResult();
        if (! $result instanceof ViewModel) {
            return;
        }

        $model = $e->getViewModel();

        if ($result->terminate()) {
            $e->setViewModel(viewModel: $result);
            return;
        }

        if ($e->getError() && $model instanceof ClearableModelInterface) {
            $model->clearChildren();
        }

        $model->addChild(child: $result);
    }
}
