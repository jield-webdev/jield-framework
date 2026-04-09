<?php

namespace Jield\Mvc\View\Http;

use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface as Events;
use Jield\Mvc\MvcEvent;
use Jield\Stdlib\ArrayUtils;
use Laminas\View\Model\ViewModel;

class CreateViewModelListener extends AbstractListenerAggregate
{
    /**
     * {@inheritDoc}
     */
    public function attach(Events $events, int $priority = 1): void
    {
        $this->listeners[] = $events->attach(eventName: MvcEvent::EVENT_DISPATCH, listener: [$this, 'createViewModelFromArray'], priority: -80);
        $this->listeners[] = $events->attach(eventName: MvcEvent::EVENT_DISPATCH, listener: [$this, 'createViewModelFromNull'], priority: -80);
    }

    /**
     * Inspect the result, and cast it to a ViewModel if an assoc array is detected
     *
     * @return void
     */
    public function createViewModelFromArray(MvcEvent $e): void
    {
        $result = $e->getResult();
        if (! ArrayUtils::hasStringKeys(value: $result, allowEmpty: true)) {
            return;
        }

        $model = new ViewModel(variables: $result);
        $e->setResult(result: $model);
    }

    /**
     * Inspect the result, and cast it to a ViewModel if null is detected
     *
     * @return void
     */
    public function createViewModelFromNull(MvcEvent $e): void
    {
        $result = $e->getResult();
        if (null !== $result) {
            return;
        }

        $model = new ViewModel();
        $e->setResult(result: $model);
    }
}
