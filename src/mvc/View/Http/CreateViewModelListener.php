<?php

declare(strict_types=1);

namespace Jield\Mvc\View\Http;

use Jield\Mvc\MvcEvent;
use Jield\Mvc\View\Model\ViewModel;
use Jield\Stdlib\Value;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface as Events;
use Laminas\Stdlib\ArrayUtils;

class CreateViewModelListener extends AbstractListenerAggregate
{
    /**
     * {@inheritDoc}
     */
    public function attach(Events $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_DISPATCH, $this->createViewModelFromArray(...), -80);
        $this->listeners[] = $events->attach(MvcEvent::EVENT_DISPATCH, $this->createViewModelFromNull(...), -80);
    }

    /**
     * Inspect the result, and cast it to a ViewModel if an assoc array is detected
     *
     * @return void
     */
    public function createViewModelFromArray(MvcEvent $e): void
    {
        $result = $e->getResult();
        if (!ArrayUtils::hasStringKeys($result, true)) {
            return;
        }

        $model = new ViewModel(Value::namedValues($result));
        $e->setResult($model);
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
        $e->setResult($model);
    }
}
