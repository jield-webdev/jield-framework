<?php

declare(strict_types=1);

namespace Jield\Mvc\View\Http;

use Jield\Mvc\MvcEvent;
use Jield\Mvc\View\Model\JsonModel;
use Jield\Stdlib\Value;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Http\AbstractMessage;

final class JsonStrategy extends AbstractListenerAggregate
{
    public function attach(EventManagerInterface $events, $priority = 100): void
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_RENDER, $this->render(...), $priority + 100);
    }

    public function render(MvcEvent $event): void
    {
        $model = $event->getResult();
        if (!$model instanceof JsonModel) {
            $model = $event->getViewModel();
        }
        if (!$model instanceof JsonModel) {
            return;
        }
        $response = $event->getResponse();
        $response->setContent($model->serialize());
        Value::instance($response, AbstractMessage::class)
            ->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');
        $event->setResult($response);
        $event->stopPropagation();
    }
}
