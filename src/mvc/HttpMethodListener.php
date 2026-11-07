<?php

declare(strict_types=1);

namespace Jield\Mvc;

use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Http\Request as HttpRequest;
use Laminas\Http\Response as HttpResponse;

use function in_array;
use function strtoupper;

class HttpMethodListener extends AbstractListenerAggregate
{
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

    protected bool $enabled = true;

    /**
     * @param bool  $enabled
     * @param array $allowedMethods
     */
    public function __construct($enabled = true, $allowedMethods = [])
    {
        $this->setEnabled($enabled);

        if (!empty($allowedMethods)) {
            $this->setAllowedMethods($allowedMethods);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->listeners[] = $events->attach(MvcEvent::EVENT_ROUTE, $this->onRoute(...), 10_000);
    }

    /**
     * @return void|HttpResponse
     */
    public function onRoute(MvcEvent $e)
    {
        $request = $e->getRequest();
        $response = $e->getResponse();

        if (!$request instanceof HttpRequest || !$response instanceof HttpResponse) {
            return;
        }

        $method = $request->getMethod();

        if (in_array($method, $this->getAllowedMethods(), strict: true)) {
            return;
        }

        $response->setStatusCode(405);

        return $response;
    }

    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }

    public function setAllowedMethods(array $allowedMethods): void
    {
        /** @mago-var mixed $value */
        foreach ($allowedMethods as &$value) {
            $value = strtoupper(\Webmozart\Assert\Assert::string($value));
        }
        $this->allowedMethods = $allowedMethods;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param bool $enabled
     */
    public function setEnabled($enabled): void
    {
        $this->enabled = $enabled;
    }
}
