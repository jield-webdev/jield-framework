<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation;

use Jield\ApiTools\ApiProblem\ApiProblem;
use Jield\ApiTools\ApiProblem\ApiProblemResponse;
use Jield\Mvc\MvcEvent;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Http\Header\ContentType;
use Laminas\Stdlib\ArrayUtils;
use Override;

use function method_exists;

class ContentTypeFilterListener extends AbstractListenerAggregate
{
    /**
     * Whitelist configuration
     *
     * @var array
     */
    protected array $config = [];

    /**
     * @param int                    $priority
     */
    #[Override]
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(
            eventName: MvcEvent::EVENT_ROUTE,
            listener: $this->onRoute(...),
            priority: -625,
        );
    }

    /**
     * Set whitelist configuration
     *
     * @param  array $config
     * @return static
     */
    public function setConfig(array $config): static
    {
        $this->config = ArrayUtils::merge(a: $this->config, b: $config);
        return $this;
    }

    /**
     * Test if the content-type received is allowable.
     *
     */
    public function onRoute(MvcEvent $e): ?ApiProblemResponse
    {
        if (empty($this->config)) {
            return null;
        }

        /** @var mixed $controllerName */
        $controllerName = \Jield\Stdlib\Value::present($e->getRouteMatch())->getParam(name: 'controller');
        if (!isset($this->config[$controllerName])) {
            return null;
        }

        // Only worry about content types on HTTP methods that submit content
        // via the request body.
        $request = $e->getRequest();
        if (!method_exists(object_or_class: $request, method: 'getHeaders')) {
            // Not an HTTP request; nothing to do
            return null;
        }

        $requestBody = (string) $request->getContent();

        if ($requestBody === '' || $requestBody === '0') {
            return null;
        }

        /** @var mixed $headers */
        $headers = $request->getHeaders();
        if (!\Jield\Stdlib\Value::call($headers, 'has', ['content-type'])) {
            return new ApiProblemResponse(apiProblem: new ApiProblem(
                status: 415,
                detail: 'Invalid content-type specified',
            ));
        }

        /** @var mixed $contentTypeHeader */
        $contentTypeHeader = \Jield\Stdlib\Value::call($headers, 'get', ['content-type']);
        if (!$contentTypeHeader instanceof ContentType) {
            return new ApiProblemResponse(apiProblem: new ApiProblem(
                status: 415,
                detail: 'Invalid content-type specified',
            ));
        }

        $matched = $contentTypeHeader->match(\Jield\Stdlib\Value::arrayOrString($this->config[$controllerName]));

        if (false === $matched) {
            return new ApiProblemResponse(apiProblem: new ApiProblem(
                status: 415,
                detail: 'Invalid content-type specified',
            ));
        }

        return null;
    }
}
