<?php

namespace Jield\Mvc;

use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Jield\Http\Request as HttpRequest;
use Jield\Http\Response as HttpResponse;

use function in_array;
use function strtoupper;

class HttpMethodListener extends AbstractListenerAggregate
{
    /** @var array */
    protected array $allowedMethods = [
        HttpRequest::METHOD_CONNECT,
        HttpRequest::METHOD_DELETE,
        HttpRequest::METHOD_GET,
        HttpRequest::METHOD_HEAD,
        HttpRequest::METHOD_OPTIONS,
        HttpRequest::METHOD_PATCH,
        HttpRequest::METHOD_POST,
        HttpRequest::METHOD_PUT,
        HttpRequest::METHOD_PROPFIND,
        HttpRequest::METHOD_TRACE,
    ];

    /** @var bool */
    protected bool $enabled = true;

    /**
     * @param bool $enabled
     * @param array $allowedMethods
     */
    public function __construct(bool $enabled = true, array $allowedMethods = [])
    {
        $this->setEnabled(enabled: $enabled);

        if (! empty($allowedMethods)) {
            $this->setAllowedMethods(allowedMethods: $allowedMethods);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function attach(EventManagerInterface $events, int $priority = 1): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->listeners[] = $events->attach(
            eventName: MvcEvent::EVENT_ROUTE,
            listener: [$this, 'onRoute'],
            priority: 10000
        );
    }

    /**
     * @return void|HttpResponse
     */
    public function onRoute(MvcEvent $e)
    {
        $request  = $e->getRequest();
        $response = $e->getResponse();

        if (! $request instanceof HttpRequest || ! $response instanceof HttpResponse) {
            return;
        }

        $method = $request->getMethod();

        if (in_array(needle: $method, haystack: $this->getAllowedMethods())) {
            return;
        }

        $response->setStatusCode(code: 405);

        return $response;
    }

    /**
     * @return array
     */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }

    public function setAllowedMethods(array $allowedMethods): void
    {
        foreach ($allowedMethods as &$value) {
            $value = strtoupper(string: $value);
        }
        $this->allowedMethods = $allowedMethods;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param bool $enabled
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }
}
