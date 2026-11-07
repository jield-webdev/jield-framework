<?php

declare(strict_types=1);

namespace Jield\ApiTools\ApiProblem\View;

use Jield\Mvc\MvcEvent;
use Jield\Stdlib\Value;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Http\AbstractMessage;
use Laminas\Http\Response;

class ApiProblemStrategy extends AbstractListenerAggregate
{
    public function __construct(
        private readonly ApiProblemRenderer $renderer,
    ) {}

    public function attach(EventManagerInterface $events, $priority = 300): void
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_RENDER, $this->render(...), $priority);
        $this->listeners[] = $events->attach(MvcEvent::EVENT_RENDER_ERROR, $this->render(...), $priority);
    }

    public function render(MvcEvent $event): void
    {
        $model = $event->getResult();
        if (!$model instanceof ApiProblemModel) {
            $model = $event->getViewModel();
        }
        if (!$model instanceof ApiProblemModel) {
            return;
        }
        $body = $this->renderer->render($model);
        $problem = $model;
        $response = $event->getResponse();
        $status = $problem->getApiProblem()->getStatus();
        Value::instance($response, Response::class)->setStatusCode(
            $status >= 100 && $status <= 599 ? $status : 500,
        );
        $response->setContent($body);
        Value::instance($response, AbstractMessage::class)
            ->getHeaders()
            ->addHeaderLine('Content-Type', 'application/problem+json');
        $event->setResult($response);
        $event->stopPropagation();
    }
}
