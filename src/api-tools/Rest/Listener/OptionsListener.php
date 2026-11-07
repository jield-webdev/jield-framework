<?php

declare(strict_types=1);

namespace Jield\ApiTools\Rest\Listener;

use Jield\Mvc\MvcEvent;
use Jield\Mvc\Router\RouteMatch;
use Jield\Stdlib\Value;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;
use Laminas\EventManager\ListenerAggregateTrait;
use Laminas\Http\Request;
use Laminas\Http\Response;
use Override;

use function array_key_exists;
use function array_walk;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function strtoupper;

class OptionsListener implements ListenerAggregateInterface
{
    use ListenerAggregateTrait;

    /**
     * @param array $config
     */
    public function __construct(
        protected array $config,
    ) {}

    /**
     * @param int $priority
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
        $request = $event->getRequest();
        if (!$request instanceof Request) {
            // Not an HTTP request? nothing to do
            return null;
        }

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

        if (!array_key_exists(key: Value::key($controller), array: $this->config)) {
            // No matching controller in our configuration, nothing to do
            return null;
        }

        $config = $this->getConfigForControllerAndMatches(
            config: Value::array($this->config[$controller]),
            matches: $matches,
        );
        $methods = $this->normalizeMethods(methods: $config);

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
     * @param array|string $methods
     * @return array
     */
    protected function normalizeMethods(array|string $methods): array|string
    {
        if (is_string(value: $methods)) {
            $methods = (array) $methods;
        }

        array_walk(array: $methods, callback: static fn(&$value) => strtoupper(string: (string) $value));
        return $methods;
    }

    /**
     * Create the Allow header
     *
     */
    protected function createAllowHeader(array $options, Response $response): void
    {
        $headers = $response->getHeaders();
        $headers->addHeaderLine(headerFieldNameOrLine: 'Allow', fieldValue: implode(
            separator: ',',
            array: Value::scalars($options),
        ));
    }

    /**
     * Prepare and return an OPTIONS response
     *
     * Creates an empty response with an Allow header.
     *
     */
    protected function getOptionsResponse(MvcEvent $event, array $options): Response
    {
        /** @var Response $response */
        $response = $event->getResponse();
        $this->createAllowHeader(options: $options, response: $response);
        return $response;
    }

    /**
     * Prepare a 405 response
     *
     */
    protected function get405Response(MvcEvent $event, array $options): Response
    {
        $response = $this->getOptionsResponse(event: $event, options: $options);
        $response->setStatusCode(code: 405);
        return $response;
    }

    /**
     * Retrieve the HTTP method configuration for the selected controller and request
     *
     * Determines if this was a request to a collection or an entity, and returns the
     * appropriate HTTP method configuration.
     *
     * If an entity request was detected, but no entity configuration exists, returns
     * empty array.
     *
     * @param RouteMatch $matches
     */
    protected function getConfigForControllerAndMatches(array $config, RouteMatch $matches): array
    {
        $collectionConfig = [];
        if (
            array_key_exists(key: 'collection_http_methods', array: $config)
            && is_array(value: $config['collection_http_methods'])
        ) {
            $collectionConfig = $config['collection_http_methods'];
            // Ensure the HTTP method names are normalized
            array_walk(array: $collectionConfig, callback: static function (&$value): void {
                $value = strtoupper(string: Value::string($value));
            });
        }

        $identifier = false;
        if (array_key_exists(key: 'route_identifier_name', array: $config)) {
            $identifier = $config['route_identifier_name'];
        }

        if (
            !$identifier
            || $matches->getParam(name: Value::string($identifier), default: false) === false
        ) {
            return $collectionConfig;
        }

        if (
            array_key_exists(key: 'entity_http_methods', array: $config)
            && is_array(value: $config['entity_http_methods'])
        ) {
            $entityConfig = $config['entity_http_methods'];
            // Ensure the HTTP method names are normalized
            array_walk(array: $entityConfig, callback: static function (&$value): void {
                $value = strtoupper(string: Value::string($value));
            });
            return $entityConfig;
        }

        return [];
    }
}
