<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\View;

use Jield\ApiTools\ApiProblem\View\ApiProblemModel;
use Jield\Mvc\MvcEvent;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;

class HalJsonStrategy extends AbstractListenerAggregate
{
    public function __construct(
        private readonly HalJsonRenderer $renderer,
    ) {}

    public function attach(EventManagerInterface $events, $priority = 300): void
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_RENDER, $this->render(...), $priority);
        $this->listeners[] = $events->attach(MvcEvent::EVENT_RENDER_ERROR, $this->render(...), $priority);
    }

    public function render(MvcEvent $event): void
    {
        /** @var mixed $model */
        $model = $event->getResult();
        if (!$model instanceof HalJsonModel) {
            $model = $event->getViewModel();
        }
        if (!$model instanceof HalJsonModel) {
            return;
        }
        $this->renderer->setViewEvent($event);
        $body = $this->renderer->render($model);
        /** @var mixed $problem */
        $problem = $event->getResult();
        $response = $event->getResponse();
        if ($problem instanceof ApiProblemModel) {
            $status = $problem->getApiProblem()->getStatus();
            \Jield\Stdlib\Value::instance($response, \Laminas\Http\Response::class)->setStatusCode($status >= 100 && $status <= 599 ? $status : 500);
        }
        $response->setContent($body);
        \Jield\Stdlib\Value::instance($response, \Laminas\Http\AbstractMessage::class)->getHeaders()->addHeaderLine(
            'Content-Type',
            $problem instanceof ApiProblemModel ? 'application/problem+json' : 'application/hal+json',
        );
        $event->setResult($response);
        $event->stopPropagation();
    }
}
