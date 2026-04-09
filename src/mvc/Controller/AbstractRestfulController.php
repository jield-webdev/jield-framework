<?php

namespace Jield\Mvc\Controller;

use Jield\Http\Header\ContentType;
use Jield\Http\Request as HttpRequest;
use Jield\Mvc\Exception;
use Jield\Mvc\Exception\DomainException;
use Jield\Mvc\Exception\InvalidArgumentException;
use Jield\Mvc\Exception\RuntimeException;
use Jield\Mvc\MvcEvent;
use Laminas\Router\RouteMatch;
use Jield\Stdlib\RequestInterface as Request;
use Jield\Stdlib\ResponseInterface as Response;

use function array_key_exists;
use function array_shift;
use function call_user_func;
use function count;
use function explode;
use function get_debug_type;
use function is_array;
use function is_callable;
use function json_decode;
use function method_exists;
use function parse_str;
use function reset;
use function sprintf;
use function str_contains;
use function stripos;
use function strtolower;
use function trim;

/**
 * Abstract RESTful controller
 */
abstract class AbstractRestfulController extends AbstractController
{
    public const string CONTENT_TYPE_JSON = 'json';

    /**
     * {@inheritDoc}
     */
    protected string|array|null $eventIdentifier = self::class;

    /** @var array */
    protected array $contentTypes = [
        self::CONTENT_TYPE_JSON => [
            'application/hal+json',
            'application/json',
        ],
    ];

    /**
     * Name of request or query parameter containing identifier
     *
     * @var string
     */
    protected string $identifierName = 'id';

    /**
     * Flag to pass to json_decode.
     *
     * Default value is boolean true, meaning JSON should be cast to
     * associative arrays (vs objects).
     *
     * Override the value in an extending class to set the default behavior
     * for your class.
     *
     * @var bool
     */
    protected bool $jsonDecodeType = true;

    /**
     * Map of custom HTTP methods and their handlers
     *
     * @var array
     */
    protected array $customHttpMethodsMap = [];

    /**
     * Set the route match/query parameter name containing the identifier
     *
     * @param string $name
     * @return self
     */
    public function setIdentifierName(string $name): static
    {
        $this->identifierName = $name;
        return $this;
    }

    /**
     * Retrieve the route match/query parameter name containing the identifier
     *
     * @return string
     */
    public function getIdentifierName(): string
    {
        return $this->identifierName;
    }

    /**
     * Create a new resource
     *
     * @return mixed
     */
    public function create(mixed $data): mixed
    {
        $this->response->setStatusCode(code: 405);

        return [
            'content' => 'Method Not Allowed',
        ];
    }

    /**
     * Delete an existing resource
     *
     * @return mixed
     */
    public function delete(mixed $id): mixed
    {
        $this->response->setStatusCode(code: 405);

        return [
            'content' => 'Method Not Allowed',
        ];
    }

    /**
     * Delete the entire resource collection
     *
     * Not marked as abstract, as that would introduce a BC break
     * (introduced in 2.1.0); instead, raises an exception if not implemented.
     *
     * @param mixed $data
     * @return mixed
     */
    public function deleteList(mixed $data): mixed
    {
        $this->response->setStatusCode(code: 405);

        return [
            'content' => 'Method Not Allowed',
        ];
    }

    /**
     * Return single resource
     *
     * @return mixed
     */
    public function get(mixed $id): mixed
    {
        $this->response->setStatusCode(code: 405);

        return [
            'content' => 'Method Not Allowed',
        ];
    }

    /**
     * Return list of resources
     *
     * @return mixed
     */
    public function getList(): mixed
    {
        $this->response->setStatusCode(code: 405);

        return [
            'content' => 'Method Not Allowed',
        ];
    }

    /**
     * Retrieve HEAD metadata for the resource
     *
     * Not marked as abstract, as that would introduce a BC break
     * (introduced in 2.1.0); instead, raises an exception if not implemented.
     *
     * @param mixed $id
     * @return mixed
     */
    public function head(mixed $id = null): mixed
    {
        $this->response->setStatusCode(code: 405);

        return [
            'content' => 'Method Not Allowed',
        ];
    }

    /**
     * Respond to the OPTIONS method
     *
     * Typically, set the Allow header with allowed HTTP methods, and
     * return the response.
     *
     * Not marked as abstract, as that would introduce a BC break
     * (introduced in 2.1.0); instead, raises an exception if not implemented.
     *
     * @return mixed
     */
    public function options(): mixed
    {
        $this->response->setStatusCode(code: 405);

        return [
            'content' => 'Method Not Allowed',
        ];
    }

    /**
     * Respond to the PATCH method
     *
     * Not marked as abstract, as that would introduce a BC break
     * (introduced in 2.1.0); instead, raises an exception if not implemented.
     *
     * @param mixed  $id
     * @param mixed $data
     * @return mixed
     */
    public function patch(mixed $id, mixed $data): mixed
    {
        $this->response->setStatusCode(code: 405);

        return [
            'content' => 'Method Not Allowed',
        ];
    }

    /**
     * Replace an entire resource collection
     *
     * Not marked as abstract, as that would introduce a BC break
     * (introduced in 2.1.0); instead, raises an exception if not implemented.
     *
     * @return mixed
     */
    public function replaceList(mixed $data): mixed
    {
        $this->response->setStatusCode(code: 405);

        return [
            'content' => 'Method Not Allowed',
        ];
    }

    /**
     * Modify a resource collection without completely replacing it
     *
     * Not marked as abstract, as that would introduce a BC break
     * (introduced in 2.2.0); instead, raises an exception if not implemented.
     *
     * @return mixed
     */
    public function patchList(mixed $data): mixed
    {
        $this->response->setStatusCode(code: 405);

        return [
            'content' => 'Method Not Allowed',
        ];
    }

    /**
     * Update an existing resource
     *
     * @return mixed
     */
    public function update(mixed $id, mixed $data): mixed
    {
        $this->response->setStatusCode(code: 405);

        return [
            'content' => 'Method Not Allowed',
        ];
    }

    /**
     * Basic functionality for when a page is not available
     *
     * @return array
     */
    public function notFoundAction(): array
    {
        $this->response->setStatusCode(code: 404);

        return [
            'content' => 'Page not found',
        ];
    }

    /**
     * Dispatch a request
     *
     * If the route match includes an "action" key, then this acts basically like
     * a standard action controller. Otherwise, it introspects the HTTP method
     * to determine how to handle the request, and which method to delegate to.
     *
     * @events dispatch.pre, dispatch.post
     * @return mixed|Response
     * @throws Exception\InvalidArgumentException
     */
    public function dispatch(Request $request, ?Response $response = null): mixed
    {
        if (! $request instanceof HttpRequest) {
            throw new InvalidArgumentException(message: 'Expected an HTTP request');
        }

        return parent::dispatch(request: $request, response: $response);
    }

    /**
     * Handle the request
     *
     * @todo   try-catch in "patch" for patchList should be removed in the future
     * @return mixed
     * @throws Exception\DomainException If no route matches in event or invalid HTTP method.
     */
    public function onDispatch(MvcEvent $e): mixed
    {
        $routeMatch = $e->getRouteMatch();
        if (! $routeMatch) {
            /**
             * @todo Determine requirements for when route match is missing.
             *       Potentially allow pulling directly from request metadata?
             */
            throw new DomainException(message: 'Missing route matches; unsure how to retrieve action');
        }

        $request = $e->getRequest();

        // Was an "action" requested?
        $action = $routeMatch->getParam(name: 'action', default: false);
        if ($action) {
            // Handle arbitrary methods, ending in Action
            $method = static::getMethodFromAction(action: $action);
            if (! method_exists(object_or_class: $this, method: $method)) {
                $method = 'notFoundAction';
            }
            $return = $this->$method();
            $e->setResult(result: $return);
            return $return;
        }

        // RESTful methods
        $method = strtolower(string: $request->getMethod());
        switch ($method) {
            // Custom HTTP methods (or custom overrides for standard methods)
            case isset($this->customHttpMethodsMap[$method]):
                $callable = $this->customHttpMethodsMap[$method];
                $action   = $method;
                $return   = call_user_func($callable, $e);
                break;
            // DELETE
            case 'delete':
                $id = $this->getIdentifier(routeMatch: $routeMatch, request: $request);

                if ($id !== false) {
                    $action = 'delete';
                    $return = $this->delete(id: $id);
                    break;
                }

                $data = $this->processBodyContent(request: $request);

                $action = 'deleteList';
                $return = $this->deleteList(data: $data);
                break;
            // GET
            case 'get':
                $id = $this->getIdentifier(routeMatch: $routeMatch, request: $request);
                if ($id !== false) {
                    $action = 'get';
                    $return = $this->get(id: $id);
                    break;
                }
                $action = 'getList';
                $return = $this->getList();
                break;
            // HEAD
            case 'head':
                $id = $this->getIdentifier(routeMatch: $routeMatch, request: $request);
                if ($id === false) {
                    $id = null;
                }
                $action     = 'head';
                $headResult = $this->head(id: $id);
                $response   = $headResult instanceof Response ? clone $headResult : $e->getResponse();
                $response->setContent('');
                $return = $response;
                break;
            // OPTIONS
            case 'options':
                $action = 'options';
                $this->options();
                $return = $e->getResponse();
                break;
            // PATCH
            case 'patch':
                $id   = $this->getIdentifier(routeMatch: $routeMatch, request: $request);
                $data = $this->processBodyContent(request: $request);

                if ($id !== false) {
                    $action = 'patch';
                    $return = $this->patch(id: $id, data: $data);
                    break;
                }

                // TODO: This try-catch should be removed in the future, but it
                // will create a BC break for pre-2.2.0 apps that expect a 405
                // instead of going to patchList
                try {
                    $action = 'patchList';
                    $return = $this->patchList(data: $data);
                } catch (RuntimeException) {
                    $response = $e->getResponse();
                    $response->setStatusCode(code: 405);
                    return $response;
                }
                break;
            // POST
            case 'post':
                $action = 'create';
                $return = $this->processPostData(request: $request);
                break;
            // PUT
            case 'put':
                $id   = $this->getIdentifier(routeMatch: $routeMatch, request: $request);
                $data = $this->processBodyContent(request: $request);

                if ($id !== false) {
                    $action = 'update';
                    $return = $this->update(id: $id, data: $data);
                    break;
                }

                $action = 'replaceList';
                $return = $this->replaceList(data: $data);
                break;
            // All others...
            default:
                $response = $e->getResponse();
                $response->setStatusCode(code: 405);
                return $response;
        }

        $routeMatch->setParam(name: 'action', value: $action);
        $e->setResult(result: $return);
        return $return;
    }

    /**
     * Process post data and call create
     *
     * @return mixed
     * @throws Exception\DomainException If a JSON request was made, but no method for parsing JSON is available.
     */
    public function processPostData(Request $request): mixed
    {
        if ($this->requestHasContentType(request: $request, contentType: self::CONTENT_TYPE_JSON)) {
            return $this->create(data: $this->jsonDecode(string: $request->getContent()));
        }

        return $this->create(data: $request->getPost()->toArray());
    }

    /**
     * Check if request has certain content type
     *
     * @param string|null $contentType
     * @return bool
     */
    public function requestHasContentType(Request $request, ?string $contentType = ''): bool
    {
        /** @var ContentType $headerContentType */
        $headerContentType = $request->getHeaders()->get('content-type');
        if (! $headerContentType) {
            return false;
        }

        $requestedContentType = $headerContentType->getFieldValue();
        if (str_contains(haystack: $requestedContentType, needle: ';')) {
            $headerData           = explode(separator: ';', string: $requestedContentType);
            $requestedContentType = array_shift(array: $headerData);
        }
        $requestedContentType = trim(string: $requestedContentType);
        if (array_key_exists(key: $contentType, array: $this->contentTypes)) {
            foreach ($this->contentTypes[$contentType] as $contentTypeValue) {
                if (stripos(haystack: $contentTypeValue, needle: $requestedContentType) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Register a handler for a custom HTTP method
     *
     * This method allows you to handle arbitrary HTTP method types, mapping
     * them to callables. Typically, these will be methods of the controller
     * instance: e.g., array($this, 'foobar'). The typical place to register
     * these is in your constructor.
     *
     * Additionally, as this map is checked prior to testing the standard HTTP
     * methods, this is a way to override what methods will handle the standard
     * HTTP methods. However, if you do this, you will have to retrieve the
     * identifier and any request content manually.
     *
     * Callbacks will be passed the current MvcEvent instance.
     *
     * To retrieve the identifier, you can use $id =this->getIdentifier($routeMatch, $request),
     * passing the appropriate objects.
     *
     * To retrieve the body content data, use $data = $this->processBodyContent($request);
     * that method will return a string, array, or, in the case of JSON, an object.
     *
     * @param string $method
     * @param Callable $handler
     * @return AbstractRestfulController
     */
    public function addHttpMethodHandler(string $method, callable $handler): static
    {
        if (! is_callable(value: $handler)) {
            throw new InvalidArgumentException(message: sprintf(
                'Invalid HTTP method handler: must be a callable; received "%s"',
                get_debug_type(value: $handler)
            ));
        }
        $method                              = strtolower(string: $method);
        $this->customHttpMethodsMap[$method] = $handler;
        return $this;
    }

    /**
     * Retrieve the identifier, if any
     *
     * Attempts to see if an identifier was passed in either the URI or the
     * query string, returning it if found. Otherwise, returns a boolean false.
     *
     * @param RouteMatch $routeMatch
     * @param Request $request
     * @return false|mixed
     */
    protected function getIdentifier(RouteMatch $routeMatch, Request $request): mixed
    {
        $identifier = $this->getIdentifierName();
        $id         = $routeMatch->getParam(name: $identifier, default: false);
        if ($id !== false) {
            return $id;
        }

        $id = $request->getQuery()->get($identifier, false);
        if ($id !== false) {
            return $id;
        }

        return false;
    }

    /**
     * Process the raw body content
     *
     * If the content-type indicates a JSON payload, the payload is immediately
     * decoded and the data returned. Otherwise, the data is passed to
     * parse_str(). If that function returns a single-member array with a empty
     * value, the method assumes that we have non-urlencoded content and
     * returns the raw content; otherwise, the array created is returned.
     *
     * @return object|string|array
     * @throws Exception\DomainException If a JSON request was made, but no method for parsing JSON is available.
     */
    protected function processBodyContent(mixed $request): object|array|string
    {
        $content = $request->getContent();

        // JSON content? decode and return it.
        if ($this->requestHasContentType(request: $request, contentType: self::CONTENT_TYPE_JSON)) {
            return $this->jsonDecode(string: $request->getContent());
        }

        parse_str(string: $content, result: $parsedParams);

        // If parse_str fails to decode, or we have a single element with empty value
        if (
            ! is_array(value: $parsedParams) || empty($parsedParams)
            || (1 === count(value: $parsedParams) && '' === reset(array: $parsedParams))
        ) {
            return $content;
        }

        return $parsedParams;
    }

    /**
     * Decode a JSON string.
     *
     * Uses json_decode by default.
     *
     * Marked protected to allow usage from extending classes.
     *
     * @param string $string
     * @return mixed
     */
    protected function jsonDecode(string $string): mixed
    {
        return json_decode(json: $string, associative: $this->jsonDecodeType);
    }
}
