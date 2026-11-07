<?php

declare(strict_types=1);

namespace Jield\Mvc\Controller;

use Jield\Mvc\Exception;
use Jield\Mvc\Exception\DomainException;
use Jield\Mvc\Exception\InvalidArgumentException;
use Jield\Mvc\Exception\RuntimeException;
use Jield\Mvc\MvcEvent;
use Jield\Mvc\Router\RouteMatch;
use Jield\Stdlib\Value;
use Laminas\Http\AbstractMessage;
use Laminas\Http\Header\ContentType;
use Laminas\Http\Request as HttpRequest;
use Laminas\Stdlib\RequestInterface as Request;
use Laminas\Stdlib\ResponseInterface as Response;
use Webmozart\Assert\Assert;

use function array_key_exists;
use function array_shift;
use function explode;
use function get_debug_type;
use function is_callable;
use function json_decode;
use function method_exists;
use function parse_str;
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

    protected string|array|null $eventIdentifier = self::class;

    protected array $contentTypes = [
        self::CONTENT_TYPE_JSON => [
            'application/hal+json',
            'application/json',
        ],
    ];

    /**
     * Name of request or query parameter containing identifier
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
     */
    protected bool $jsonDecodeType = true;

    /**
     * Map of custom HTTP methods and their handlers
     *
     * @var array<string, callable(MvcEvent): mixed>
     */
    protected array $customHttpMethodsMap = [];

    /**
     * Set the route match/query parameter name containing the identifier
     *
     * @param  string $name
     */
    public function setIdentifierName($name): static
    {
        $this->identifierName = $name;
        return $this;
    }

    /**
     * Retrieve the route match/query parameter name containing the identifier
     */
    public function getIdentifierName(): string
    {
        return $this->identifierName;
    }

    /**
     * Create a new resource
     */
    public function create(mixed $data): mixed
    {
        Assert::isInstanceOf($this->response, \Laminas\Http\Response::class)->setStatusCode(405);

        return [
            'content' => 'Method Not Allowed',
        ];
    }

    /**
     * Delete an existing resource
     */
    public function delete(mixed $id): mixed
    {
        Assert::isInstanceOf($this->response, \Laminas\Http\Response::class)->setStatusCode(405);

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
     */
    public function deleteList($data): mixed
    {
        Assert::isInstanceOf($this->response, \Laminas\Http\Response::class)->setStatusCode(405);

        return [
            'content' => 'Method Not Allowed',
        ];
    }

    /**
     * Return single resource
     */
    public function get(mixed $id): mixed
    {
        Assert::isInstanceOf($this->response, \Laminas\Http\Response::class)->setStatusCode(405);

        return [
            'content' => 'Method Not Allowed',
        ];
    }

    /**
     * Return list of resources
     */
    public function getList(): mixed
    {
        Assert::isInstanceOf($this->response, \Laminas\Http\Response::class)->setStatusCode(405);

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
     * @param  mixed $id
     */
    public function head($id = null): mixed
    {
        Assert::isInstanceOf($this->response, \Laminas\Http\Response::class)->setStatusCode(405);

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
     */
    public function options(): mixed
    {
        Assert::isInstanceOf($this->response, \Laminas\Http\Response::class)->setStatusCode(405);

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
     */
    public function patch($id, $data): mixed
    {
        Assert::isInstanceOf($this->response, \Laminas\Http\Response::class)->setStatusCode(405);

        return [
            'content' => 'Method Not Allowed',
        ];
    }

    /**
     * Replace an entire resource collection
     *
     * Not marked as abstract, as that would introduce a BC break
     * (introduced in 2.1.0); instead, raises an exception if not implemented.
     */
    public function replaceList(mixed $data): mixed
    {
        Assert::isInstanceOf($this->response, \Laminas\Http\Response::class)->setStatusCode(405);

        return [
            'content' => 'Method Not Allowed',
        ];
    }

    /**
     * Modify a resource collection without completely replacing it
     *
     * Not marked as abstract, as that would introduce a BC break
     * (introduced in 2.2.0); instead, raises an exception if not implemented.
     */
    public function patchList(mixed $data): mixed
    {
        Assert::isInstanceOf($this->response, \Laminas\Http\Response::class)->setStatusCode(405);

        return [
            'content' => 'Method Not Allowed',
        ];
    }

    /**
     * Update an existing resource
     */
    public function update(mixed $id, mixed $data): mixed
    {
        Assert::isInstanceOf($this->response, \Laminas\Http\Response::class)->setStatusCode(405);

        return [
            'content' => 'Method Not Allowed',
        ];
    }

    /**
     * Basic functionality for when a page is not available
     */
    public function notFoundAction(): array
    {
        Assert::isInstanceOf($this->response, \Laminas\Http\Response::class)->setStatusCode(404);

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
     * @throws Exception\InvalidArgumentException
     */
    public function dispatch(Request $request, ?Response $response = null): mixed
    {
        if (!$request instanceof HttpRequest) {
            throw new InvalidArgumentException('Expected an HTTP request');
        }

        return parent::dispatch($request, $response);
    }

    /**
     * Handle the request
     *
     * @todo   try-catch in "patch" for patchList should be removed in the future
     * @throws Exception\DomainException If no route matches in event or invalid HTTP method.
     * @throws \ReflectionException
     */
    public function onDispatch(MvcEvent $e): mixed
    {
        $routeMatch = $e->getRouteMatch();
        if (!$routeMatch) {
            /**
             * @todo Determine requirements for when route match is missing.
             *       Potentially allow pulling directly from request metadata?
             */
            throw new DomainException('Missing route matches; unsure how to retrieve action');
        }

        $request = $e->getRequest();

        // Was an "action" requested?
        /** @mago-var mixed $action */
        $action = $routeMatch->getParam('action', false);
        if ($action) {
            // Handle arbitrary methods, ending in Action
            $method = static::getMethodFromAction(Assert::string($action));
            if (!method_exists($this, $method)) {
                $method = 'notFoundAction';
            }
            /** @mago-var mixed $return */
            $return = Value::call($this, $method, [], scope: self::class);
            $e->setResult($return);
            return $return;
        }

        // RESTful methods
        $method = strtolower(Assert::isInstanceOf($request, \Laminas\Http\Request::class)->getMethod());
        if (isset($this->customHttpMethodsMap[$method])) {
            /** @mago-var mixed $return */
            $return = $this->customHttpMethodsMap[$method]($e);
            $routeMatch->setParam('action', $method);
            $e->setResult($return);
            return $return;
        }
        switch ($method) {
            // DELETE
            case 'delete':
                /** @mago-var mixed $id */
                $id = $this->getIdentifier($routeMatch, $request);

                if ($id !== false) {
                    $action = 'delete';
                    /** @mago-var mixed $return */
                    $return = $this->delete($id);
                    break;
                }

                /** @mago-var mixed $data */
                $data = $this->processBodyContent($request);

                $action = 'deleteList';
                /** @mago-var mixed $return */
                $return = $this->deleteList($data);
                break;
                // GET
            case 'get':
                /** @mago-var mixed $id */
                $id = $this->getIdentifier($routeMatch, $request);
                if ($id !== false) {
                    $action = 'get';
                    /** @mago-var mixed $return */
                    $return = $this->get($id);
                    break;
                }
                $action = 'getList';
                /** @mago-var mixed $return */
                $return = $this->getList();
                break;
                // HEAD
            case 'head':
                /** @mago-var mixed $id */
                $id = $this->getIdentifier($routeMatch, $request);
                if ($id === false) {
                    $id = null;
                }
                $action = 'head';
                /** @mago-var mixed $headResult */
                $headResult = $this->head($id);
                $response = $headResult instanceof Response ? clone $headResult : $e->getResponse();
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
                /** @mago-var mixed $id */
                $id = $this->getIdentifier($routeMatch, $request);
                /** @mago-var mixed $data */
                $data = $this->processBodyContent($request);

                if ($id !== false) {
                    $action = 'patch';
                    /** @mago-var mixed $return */
                    $return = $this->patch($id, $data);
                    break;
                }

                // TODO: This try-catch should be removed in the future, but it
                // will create a BC break for pre-2.2.0 apps that expect a 405
                // instead of going to patchList
                try {
                    $action = 'patchList';
                    /** @mago-var mixed $return */
                    $return = $this->patchList($data);
                } catch (RuntimeException) {
                    $response = $e->getResponse();
                    Assert::isInstanceOf($response, \Laminas\Http\Response::class)->setStatusCode(405);
                    return $response;
                }
                break;
                // POST
            case 'post':
                $action = 'create';
                /** @mago-var mixed $return */
                $return = $this->processPostData($request);
                break;
                // PUT
            case 'put':
                /** @mago-var mixed $id */
                $id = $this->getIdentifier($routeMatch, $request);
                /** @mago-var mixed $data */
                $data = $this->processBodyContent($request);

                if ($id !== false) {
                    $action = 'update';
                    /** @mago-var mixed $return */
                    $return = $this->update($id, $data);
                    break;
                }

                $action = 'replaceList';
                /** @mago-var mixed $return */
                $return = $this->replaceList($data);
                break;
                // All others...
            default:
                $response = $e->getResponse();
                Assert::isInstanceOf($response, \Laminas\Http\Response::class)->setStatusCode(405);
                return $response;
        }

        $routeMatch->setParam('action', $action);
        $e->setResult($return);
        return $return;
    }

    /**
     * Process post data and call create
     *
     * @throws Exception\DomainException If a JSON request was made, but no method for parsing JSON is available.
     * @throws \ReflectionException
     */
    public function processPostData(Request $request): mixed
    {
        if ($this->requestHasContentType($request, self::CONTENT_TYPE_JSON)) {
            return $this->create($this->jsonDecode(Assert::string($request->getContent())));
        }

        return $this->create(Value::call(
            Assert::isInstanceOf($request, \Laminas\Http\Request::class)->getPost(),
            'toArray',
        ));
    }

    /**
     * Check if request has certain content type
     *
     * @param  string|null $contentType
     */
    public function requestHasContentType(Request $request, $contentType = ''): bool
    {
        $headerContentType = Assert::isInstanceOf($request, AbstractMessage::class)->getHeaders()->get('content-type');
        if (!$headerContentType instanceof ContentType) {
            return false;
        }

        $requestedContentType = $headerContentType->getFieldValue();
        if (str_contains($requestedContentType, ';')) {
            $headerData = explode(';', $requestedContentType);
            $requestedContentType = array_shift($headerData);
        }
        $requestedContentType = trim($requestedContentType);
        if ($contentType !== null && array_key_exists($contentType, $this->contentTypes)) {
            /** @mago-var mixed $contentTypeValue */
            foreach (Assert::isIterable($this->contentTypes[$contentType]) as $contentTypeValue) {
                if (stripos(Assert::string($contentTypeValue), $requestedContentType) === 0) {
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
     * @param mixed $handler
     * @throws InvalidArgumentException
     */
    public function addHttpMethodHandler($method, $handler): static
    {
        if (!is_callable($handler)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid HTTP method handler: must be a callable; received "%s"',
                get_debug_type($handler),
            ));
        }
        $method = strtolower($method);
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
     * @param  Request $request
     * @throws \ReflectionException
     */
    protected function getIdentifier($routeMatch, $request): mixed
    {
        $identifier = $this->getIdentifierName();
        /** @mago-var mixed $id */
        $id = $routeMatch->getParam($identifier, false);
        if ($id !== false) {
            return $id;
        }

        /** @mago-var mixed $id */
        $id = Value::call(
            Assert::isInstanceOf($request, \Laminas\Http\Request::class)->getQuery(),
            'get',
            [$identifier, false],
        );
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
     * @throws Exception\DomainException If a JSON request was made, but no method for parsing JSON is available.
     * @throws \ReflectionException
     */
    protected function processBodyContent(mixed $request): mixed
    {
        $content = Assert::string(Value::call($request, 'getContent'));

        // JSON content? decode and return it.
        if ($this->requestHasContentType(Assert::isInstanceOf($request, \Laminas\Stdlib\RequestInterface::class), self::CONTENT_TYPE_JSON)) {
            return $this->jsonDecode($content);
        }

        $parsedParams = [];
        parse_str($content, $parsedParams);

        // If parse_str fails to decode, or we have a single element with empty value
        if ($parsedParams === [] || array_values($parsedParams) === ['']) {
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
     */
    protected function jsonDecode($string): mixed
    {
        return json_decode($string, $this->jsonDecodeType);
    }
}
