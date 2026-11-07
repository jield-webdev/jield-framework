<?php

declare(strict_types=1);

namespace Jield\ApiTools\Rpc;

use Jield\Mvc\MvcEvent;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;
use Laminas\EventManager\ListenerAggregateTrait;
use Laminas\Http\Request;
use Laminas\Http\Response;
use Override;
use Stringable;
use Webmozart\Assert\Assert;

use function array_key_exists;
use function implode;
use function in_array;
use function is_string;
use function strtoupper;

class OptionsListener implements ListenerAggregateInterface
{
    use ListenerAggregateTrait;

    /**
     * @param  array $config
     */
    public function __construct(
        protected array $config,
    ) {}

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(
            eventName: MvcEvent::EVENT_ROUTE,
            listener: $this->onRoute(...),
            priority: -100,
        );
    }

    public function onRoute(MvcEvent $event): ?Response
    {
        $matches = $event->getRouteMatch();
        if (!$matches) {
            // No matches, nothing to do
            return null;
        }

        $controller = $matches->getParam(name: 'controller', default: false);
        if (!$controller) {
            // No controller in the matches, nothing to do
            return null;
        }

        if (!array_key_exists(key: $controller, array: $this->config)) {
            // No matching controller in our configuration, nothing to do
            return null;
        }

        $config = $this->config[$controller];

        if (!array_key_exists(key: 'http_methods', array: $config) || empty($config['http_methods'])) {
            // No HTTP methods set for controller, nothing to do
            return null;
        }

        $request = $event->getRequest();
        if (!$request instanceof Request) {
            // Not an HTTP request? nothing to do
            return null;
        }

        $methods = $this->normalizeMethods(
            methods: is_array($config['http_methods'])
                ? $config['http_methods']
                : Assert::string($config['http_methods']),
        );

        $method = $request->getMethod();
        if ($method === Request::METHOD_OPTIONS) {
            // OPTIONS request? return response with Allow header
            return $this->getOptionsResponse(event: $event, options: $methods);
        }

        if (in_array(needle: $method, haystack: $methods, strict: true)) {
            // Valid HTTP method; nothing to do
            return null;
        }

        // Invalid method; return 405 response
        return $this->get405Response(event: $event, options: $methods);
    }

    /**
     * Normalize an array of HTTP methods
     *
     * If a string is provided, create an array with that string.
     *
     * Ensure all options in the array are UPPERCASE.
     *
     * @param string|array<string> $methods
     * @return list<string>
     */
    protected function normalizeMethods(array|string $methods): array
    {
        if (is_string(value: $methods)) {
            $methods = (array) $methods;
        }

        $normalized = [];
        foreach ($methods as $method) {
            $normalized[] = strtoupper(string: $method);
        }

        return $normalized;
    }

    /**
     * Create the Allow header
     *
     * @psalm-param array<array-key,null|Stringable|scalar> $options
     */
    protected function createAllowHeader(array $options, Response $response): void
    {
        $headers = $response->getHeaders();
        $headers->addHeaderLine(headerFieldNameOrLine: 'Allow', fieldValue: implode(separator: ',', array: $options));
    }

    /**
     * Prepare and return an OPTIONS response
     *
     * Creates an empty response with an Allow header.
     *
     * @psalm-param array<array-key,null|Stringable|scalar> $options
     */
    protected function getOptionsResponse(MvcEvent $event, array $options): Response
    {
        $response = $event->getResponse();
        $this->createAllowHeader(options: $options, response: Assert::isInstanceOf(
            $response,
            \Laminas\Http\Response::class,
        ));
        return $response;
    }

    /**
     * Prepare a 405 response
     *
     * @psalm-param array<array-key,null|Stringable|scalar> $options
     */
    protected function get405Response(MvcEvent $event, array $options): Response
    {
        $response = $this->getOptionsResponse(event: $event, options: $options);
        $response->setStatusCode(code: 405);
        return $response;
    }
}
