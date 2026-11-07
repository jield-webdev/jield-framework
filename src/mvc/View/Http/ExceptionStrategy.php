<?php

declare(strict_types=1);

namespace Jield\Mvc\View\Http;

use Jield\Mvc\Application;
use Jield\Mvc\MvcEvent;
use Jield\Mvc\View\Model\ViewModel;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Stdlib\ResponseInterface as Response;
use Webmozart\Assert\Assert;

class ExceptionStrategy extends AbstractListenerAggregate
{
    /**
     * Display exceptions?
     *
     * @var bool
     */
    protected bool $displayExceptions = false;

    /**
     * Name of exception template
     *
     * @var string
     */
    protected string $exceptionTemplate = 'error';

    /**
     * {@inheritDoc}
     */
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_DISPATCH_ERROR, $this->prepareExceptionViewModel(...));
        $this->listeners[] = $events->attach(MvcEvent::EVENT_RENDER_ERROR, $this->prepareExceptionViewModel(...));
    }

    /**
     * Flag: display exceptions in error pages?
     *
     * @param  bool $displayExceptions
     * @return static
     */
    public function setDisplayExceptions($displayExceptions): static
    {
        $this->displayExceptions = $displayExceptions;
        return $this;
    }

    /**
     * Should we display exceptions in error pages?
     *
     * @return bool
     */
    public function displayExceptions(): bool
    {
        return $this->displayExceptions;
    }

    /**
     * Set the exception template
     *
     * @param  string $exceptionTemplate
     * @return static
     */
    public function setExceptionTemplate($exceptionTemplate): static
    {
        $this->exceptionTemplate = $exceptionTemplate;
        return $this;
    }

    /**
     * Retrieve the exception template
     *
     * @return string
     */
    public function getExceptionTemplate(): string
    {
        return $this->exceptionTemplate;
    }

    /**
     * Create an exception view model, and set the HTTP status code
     *
     * @todo   dispatch.error does not halt dispatch unless a response is
     *         returned. As such, we likely need to trigger rendering as a low
     *         priority dispatch.error event (or goto a render event) to ensure
     *         rendering occurs, and that munging of view models occurs when
     *         expected.
     * @return void
     */
    public function prepareExceptionViewModel(MvcEvent $e): void
    {
        // Do nothing if no error in the event
        $error = $e->getError();
        if (empty($error)) {
            return;
        }

        // Do nothing if the result is a response object
        $result = $e->getResult();
        if ($result instanceof Response) {
            return;
        }

        switch ($error) {
            case Application::ERROR_CONTROLLER_NOT_FOUND:
            case Application::ERROR_CONTROLLER_INVALID:
            case Application::ERROR_ROUTER_NO_MATCH:
                // Specifically not handling these
                return;

            case Application::ERROR_EXCEPTION:
            default:
                $model = new ViewModel([
                    'message' => 'An error occurred during execution; please try again later.',
                    'exception' => $e->getParam('exception'),
                    'display_exceptions' => $this->displayExceptions(),
                ]);
                $model->setTemplate(Assert::stringNotEmpty($this->getExceptionTemplate()));
                $e->setResult($model);

                $response = $e->getResponse();
                $statusCode = Assert::isInstanceOf($response, \Laminas\Http\Response::class)->getStatusCode();
                if ($statusCode === 200) {
                    Assert::isInstanceOf($response, \Laminas\Http\Response::class)->setStatusCode(500);
                }

                break;
        }
    }
}
