<?php

declare(strict_types=1);

namespace Jield\ApiTools\ApiProblem\Listener;

use Exception;
use Jield\ApiTools\ApiProblem\ApiProblem;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Jield\Http\Response as HttpResponse;
use Jield\Mvc\MvcEvent;
use Laminas\View\Exception\ExceptionInterface as ViewExceptionInterface;
use Override;
use Throwable;
use function json_encode;

/**
 * RenderErrorListener.
 *
 * Provides a listener on the render.error event, at high priority.
 */
class RenderErrorListener extends AbstractListenerAggregate
{
    /** @var bool */
    protected bool $displayExceptions = false;

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function attach(EventManagerInterface $events, int $priority = 1): void
    {
        $this->listeners[] = $events->attach(eventName: MvcEvent::EVENT_RENDER_ERROR, listener: $this->onRenderError(...), priority: 100);
    }

    /**
     * @param bool $flag
     * @return static
     */
    public function setDisplayExceptions(bool $flag): static
    {
        $this->displayExceptions = $flag;

        return $this;
    }

    /**
     * Handle rendering errors.
     *
     * Rendering errors are usually due to trying to render a template in
     * the PhpRenderer, when we have no templates.
     *
     * As such, report as an unacceptable response.
     *
     */
    public function onRenderError(MvcEvent $e): void
    {
        $response    = $e->getResponse();
        if (! $response instanceof HttpResponse) {
            return;
        }
        $status      = 406;
        $title       = 'Not Acceptable';
        $describedBy = 'https://datatracker.ietf.org/doc/html/rfc7231#section-6';
        $detail      = 'Your request could not be resolved to an acceptable representation.';
        $details     = false;

        $exception = $e->getParam(name: 'exception');
        if (
            ($exception instanceof Throwable)
            && !$exception instanceof ViewExceptionInterface
        ) {
            $code = $exception->getCode();
            $status = $code >= 100 && $code <= 600 ? $code : 500;

            $title   = 'Unexpected error';
            $detail  = $exception->getMessage();
            $details = [
                'code'    => $exception->getCode(),
                'message' => $exception->getMessage(),
                'trace'   => $exception->getTraceAsString(),
            ];
        }

        $payload = [
            'status'      => $status,
            'title'       => $title,
            'describedBy' => $describedBy,
            'detail'      => $detail,
        ];
        if ($details && $this->displayExceptions) {
            $payload['details'] = $details;
        }

        $response->getHeaders()->addHeaderLine(headerFieldNameOrLine: 'content-type', fieldValue: ApiProblem::CONTENT_TYPE);
        $response->setStatusCode(code: $status);
        $response->setContent(value: json_encode(value: $payload));

        $e->stopPropagation();
    }
}
