<?php

declare(strict_types=1);

namespace Jield\ApiTools\Rest;

use Exception;
use InvalidArgumentException;
use Jield\ApiTools\ApiProblem\ApiProblem;
use Jield\ApiTools\ApiProblem\ApiProblemResponse;
use Jield\ApiTools\ApiProblem\Exception\DomainException;
use Jield\ApiTools\ContentNegotiation\ViewModel as ContentNegotiationViewModel;
use Jield\ApiTools\Hal\Collection as HalCollection;
use Jield\ApiTools\Hal\Entity as HalEntity;
use Jield\ApiTools\Hal\Exception\InvalidArgumentException as HalInvalidArgumentException;
use Jield\ApiTools\MvcAuth\Identity\AuthenticatedIdentity;
use Jield\ApiTools\MvcAuth\Identity\IdentityInterface;
use Jield\Mvc\Controller\AbstractRestfulController;
use Jield\Mvc\MvcEvent;
use Jield\Mvc\Router\RouteMatch;
use Jield\Stdlib\Value;
use Laminas\Http\Header\Allow;
use Laminas\Http\Request;
use Laminas\Http\Response;
use Laminas\Stdlib\RequestInterface;
use Override;
use Throwable;
use Traversable;
use Webmozart\Assert\Assert;

use function array_keys;
use function is_array;
use function is_int;
use function is_object;
use function method_exists;
use function sprintf;

/**
 * Controller for handling resources.
 *
 * Extends the base AbstractRestfulController in order to provide very specific
 * semantics for building a RESTful JSON service. All operations return either
 *
 * - a HAL-compliant response with appropriate hypermedia links
 * - a Problem API-compliant response for reporting an error condition
 *
 * You may specify what specific HTTP method types you wish to respond to, and
 * OPTIONS will then report those; attempting any HTTP method falling outside
 * that list will result in a 405 (Method Not Allowed) response.
 *
 * I recommend using resource-specific factories when using this controller,
 * to allow injecting the specific resource you wish to use (and its listeners),
 * which will also allow you to have multiple instances of the controller when
 * desired.
 *
 * @see http://tools.ietf.org/html/draft-kelly-json-hal-03
 * @see http://tools.ietf.org/html/draft-nottingham-http-problem-02
 */
final class RestController extends AbstractRestfulController
{
    /**
     * HTTP methods we allow for the collections; used by options()
     *
     * HEAD and OPTIONS are always available.
     *
     * @var array
     */
    protected array $collectionHttpMethods = [
        'GET',
        'POST',
    ];

    /**
     * Name of the collections entry in a Collection
     *
     * @var string
     */
    protected string $collectionName = 'items';

    /**
     * Minimum number of entities to return per page of a collection.  If
     * $pageSize parameter is out of range an ApiProblem will be returned
     */
    protected int $minPageSize = 1;

    /**
     * Number of entities to return per page of a collection.  If
     * $pageSize parameter is specified, then it will override this when
     * provided in a request.
     */
    protected int $pageSize = 30;

    /**
     * Maximum number of entities to return per page of a collection.  If
     * $pageSize parameter is out of range an ApiProblem will be returned
     */
    protected int $maxPageSize = 9999;

    /**
     * A query parameter to use to specify the number of records to return in
     * each collection page.  If not provided, $pageSize will be used as a
     * default value.
     *
     * Leave null to disable this functionality and always use $pageSize.
     */
    protected string $pageSizeParam = 'pageSize';

    protected ?ResourceInterface $resource = null;

    /**
     * HTTP methods we allow for individual entities; used by options()
     *
     * HEAD and OPTIONS are always available.
     *
     * @var array
     */
    protected array $entityHttpMethods = [
        'DELETE',
        'GET',
        'PATCH',
        'PUT',
    ];

    /**
     * Route name that resolves to this resource; used to generate links.
     */
    protected ?string $route = null;

    /**
     * Constructor
     *
     * Allows you to set the event identifier, which can be useful to allow multiple
     * instances of this controller to react to different sets of shared events.
     */
    public function __construct(?string $eventIdentifier = null)
    {
        if (null !== $eventIdentifier) {
            $this->eventIdentifier = $eventIdentifier;
        }
    }

    /**
     * Set the allowed HTTP methods for collections
     */
    public function setCollectionHttpMethods(array $methods): void
    {
        $this->collectionHttpMethods = $methods;
    }

    /**
     * Set the name to which to assign a collection in a Collection
     *
     * @param string $name
     */
    public function setCollectionName(string $name): void
    {
        $this->collectionName = $name;
    }

    /**
     * Set the minimum page size for paginated responses
     *
     * @param int $count
     */
    public function setMinPageSize(int $count): void
    {
        $this->minPageSize = $count;
    }

    /**
     * Return the minimum page size
     */
    public function getMinPageSize(): int
    {
        return $this->minPageSize;
    }

    /**
     * Set the default page size for paginated responses
     */
    public function setPageSize(int $count): void
    {
        $this->pageSize = $count;
    }

    /**
     * Return the default page size
     */
    public function getPageSize(): int
    {
        return $this->pageSize;
    }

    /**
     * Set the maximum page size for paginated responses
     */
    public function setMaxPageSize(int $count): void
    {
        $this->maxPageSize = $count;
    }

    /**
     * Return the maximum page size
     */
    public function getMaxPageSize(): int
    {
        return $this->maxPageSize;
    }

    /**
     * Set the page size parameter for paginated responses.
     */
    public function setPageSizeParam(string $param): void
    {
        $this->pageSizeParam = $param;
    }

    /**
     * The true description of getIdentifierName is
     * a route identifier name.  This function corrects
     * this mistake for this controller.
     *
     * @return string
     */
    public function getRouteIdentifierName(): string
    {
        return $this->getIdentifierName();
    }

    /**
     * Inject the resource with which this controller will communicate.
     */
    public function setResource(ResourceInterface $resource): void
    {
        $this->resource = $resource;
    }

    /**
     * Returns the resource
     *
     * @return ResourceInterface
     * @throws DomainException If no resource has been set.
     */
    public function getResource(): ResourceInterface
    {
        if ($this->resource === null) {
            throw new DomainException(message: 'No resource has been set.');
        }

        $this->injectEventIdentityIntoResource();
        $this->injectEventInputFilterIntoResource();
        $this->injectRequestIntoResourceEventParams();
        return Assert::isInstanceOf($this->resource, \Jield\ApiTools\Rest\ResourceInterface::class);
    }

    /**
     * Set the allowed HTTP OPTIONS for a resource
     *
     * @param array $methods
     */
    public function setEntityHttpMethods(array $methods): void
    {
        $this->entityHttpMethods = $methods;
    }

    /**
     * Inject the route name for this resource.
     *
     * @param string $route
     */
    public function setRoute(string $route): void
    {
        $this->route = $route;
    }

    /**
     * Handle the dispatch event
     *
     * Does several "pre-flight" checks:
     * - Raises an exception if no resource is composed.
     * - Raises an exception if no route is composed.
     * - Returns a 405 response if the current HTTP request method is not in
     *   $options
     *
     * When the dispatch is complete, it will check to see if an array was
     * returned; if so, it will cast it to a view model using the
     * AcceptableViewModelSelector plugin, and the $acceptCriteria property.
     *
     * @throws DomainException
     */
    #[Override]
    public function onDispatch(MvcEvent $e): mixed
    {
        if (!$this->route) {
            throw new DomainException(message: sprintf(
                '%s requires that a route name for the resource is composed; none provided',
                self::class,
            ));
        }

        // Check for an API-Problem in the event
        /** @mago-var mixed $return */
        $return = $e->getParam(name: 'api-problem', default: false);

        // If no return value dispatch the parent event
        if (!$return) {
            /** @mago-var mixed $return */
            $return = parent::onDispatch(e: $e);
        }

        if (!$return instanceof ApiProblem && !$return instanceof HalEntity && !$return instanceof HalCollection) {
            return $return;
        }

        if ($return instanceof ApiProblem) {
            return new ApiProblemResponse(apiProblem: $return);
        }

        // Set the fallback content negotiation to use HalJson.
        $e->setParam(name: 'LaminasContentNegotiationFallback', value: 'HalJson');

        // Use content negotiation for creating the view model
        $viewModel = new ContentNegotiationViewModel(variables: ['payload' => $return]);
        $e->setResult(result: $viewModel);

        return $viewModel;
    }

    /**
     * Create a new entity
     */
    #[Override]
    public function create($data): mixed
    {
        $events = $this->getEventManager();
        $events->trigger(eventName: 'create.pre', target: $this, argv: ['data' => $data]);

        try {
            /** @mago-var mixed $value */
            $value = $this->getResource()->create(data: Value::arrayOrObject($data));
        } catch (Throwable $throwable) {
            return $this->createApiProblemFromException(e: $throwable);
        }

        if ($this->isPreparedResponse(object: $value)) {
            return $value;
        }

        if ($value instanceof HalCollection) {
            $halCollection = $this->prepareHalCollection(collection: $value);

            $events->trigger(eventName: 'create.post', target: $this, argv: [
                'data' => $data,
                'entity' => $halCollection,
                'collection' => $halCollection,
                'resource' => $halCollection,
            ]);

            return $halCollection;
        }

        $halEntity = $this->createHalEntity(entity: $value);

        if ($halEntity->getLinks()->has(relation: 'self')) {
            /** @mago-var mixed $plugin */
            $plugin = $this->plugin(name: 'Hal');
            $link = $halEntity->getLinks()->get(relation: 'self');
            /** @mago-var mixed $self */
            $self = Value::call($plugin, 'fromLink', [$link]);
            /** @mago-var mixed $url */
            $url = Value::accessible($self)['href'];

            /** @var Response $response */
            $response = $this->getResponse();
            $response->setStatusCode(code: 201);
            $response->getHeaders()->addHeaderLine(
                headerFieldNameOrLine: 'Location',
                fieldValue: Assert::nullOrString($url),
            );
            $response->getHeaders()->addHeaderLine(
                headerFieldNameOrLine: 'Content-Location',
                fieldValue: Assert::nullOrString($url),
            );
        }

        $events->trigger(eventName: 'create.post', target: $this, argv: [
            'data' => $data,
            'entity' => $halEntity,
            'resource' => $halEntity,
        ]);

        return $halEntity;
    }

    /**
     * Delete an existing entity
     */
    #[Override]
    public function delete($id): mixed
    {
        $events = $this->getEventManager();
        $events->trigger(eventName: 'delete.pre', target: $this, argv: ['id' => $id]);

        try {
            /** @mago-var mixed $result */
            $result = $this->getResource()->delete(id: Value::key($id));
        } catch (Throwable $throwable) {
            return $this->createApiProblemFromException(e: $throwable);
        }

        /** @mago-var mixed $result */
        $result = $result ?: new ApiProblem(status: 422, detail: 'Unable to delete entity.');

        if ($this->isPreparedResponse(object: $result)) {
            return $result;
        }

        /** @var Response $response */
        $response = $this->getResponse();
        $response->setStatusCode(code: 204);

        $events->trigger(eventName: 'delete.post', target: $this, argv: ['id' => $id]);

        return $response;
    }

    /**
     * Delete a collection of entities as specified.
     *
     * @param mixed $data Typically an array
     * @return Response|ApiProblem|ApiProblemResponse
     */
    #[Override]
    public function deleteList($data): Response|ApiProblem|ApiProblemResponse
    {
        $events = $this->getEventManager();
        $events->trigger(eventName: 'deleteList.pre', target: $this, argv: []);

        try {
            /** @mago-var mixed $result */
            $result = $this->getResource()->deleteList(data: Assert::isArray($data));
        } catch (Throwable $throwable) {
            return $this->createApiProblemFromException(e: $throwable);
        }

        /** @mago-var mixed $result */
        $result = $result ?: new ApiProblem(status: 422, detail: 'Unable to delete collection.');

        if ($this->isPreparedResponse(object: $result)) {
            return $result;
        }

        /** @var Response $response */
        $response = $this->getResponse();
        $response->setStatusCode(code: 204);

        $events->trigger(eventName: 'deleteList.post', target: $this, argv: []);

        return $response;
    }

    /**
     * Return single entity
     *
     * @param mixed $id
     * @return Response|ApiProblem|ApiProblemResponse|HalEntity
     */
    #[Override]
    public function get($id): HalEntity|Response|ApiProblem|ApiProblemResponse
    {
        $events = $this->getEventManager();
        $events->trigger(eventName: 'get.pre', target: $this, argv: ['id' => $id]);

        try {
            /** @mago-var mixed $entity */
            $entity = $this->getResource()->fetch(id: Value::key($id));
        } catch (Throwable $throwable) {
            return $this->createApiProblemFromException(e: $throwable);
        }

        /** @mago-var mixed $entity */
        $entity = $entity ?: new ApiProblem(status: 404, detail: 'Entity not found.');

        if ($this->isPreparedResponse(object: $entity)) {
            return $entity;
        }

        $halEntity = $this->createHalEntity(entity: $entity);

        $events->trigger(eventName: 'get.post', target: $this, argv: [
            'id' => $id,
            'entity' => $halEntity,
        ]);

        return $halEntity;
    }

    /**
     * Return collection of entities
     *
     * @return Response|HalCollection|HalEntity|ApiProblem
     */
    #[Override]
    public function getList(): mixed
    {
        $events = $this->getEventManager();
        $events->trigger(eventName: 'getList.pre', target: $this, argv: []);

        try {
            /** @mago-var mixed $collection */
            $collection = $this->getResource()->fetchAll();
        } catch (Throwable $throwable) {
            return $this->createApiProblemFromException(e: $throwable);
        }

        if ($this->isPreparedResponse(object: $collection)) {
            return $collection;
        }

        if (
            !is_array(value: $collection)
            && !$collection instanceof Traversable
            && !$collection instanceof HalCollection
            && is_object(value: $collection)
        ) {
            $halEntity = $this->createHalEntity(entity: $collection);
            $events->trigger(eventName: 'getList.post', target: $this, argv: ['collection' => $halEntity]);
            return $halEntity;
        }

        /** @var Request $request */
        $request = $this->getRequest();

        /** @mago-var mixed $pageSize */
        $pageSize = $this->pageSizeParam
            ? $request->getQuery(name: $this->pageSizeParam, default: $this->pageSize)
            : $this->pageSize;

        if (Assert::numeric($pageSize) < $this->minPageSize) {
            return new ApiProblem(status: 416, detail: sprintf(
                'Page size is out of range, minimum page size is %s',
                $this->minPageSize,
            ));
        }

        if (Assert::numeric($pageSize) > $this->maxPageSize) {
            return new ApiProblem(status: 416, detail: sprintf(
                'Page size is out of range, maximum page size is %s',
                $this->maxPageSize,
            ));
        }

        $this->setPageSize(count: (int) $pageSize);

        $halCollection = $this->createHalCollection(collection: $collection);

        if ($this->isPreparedResponse(object: $halCollection)) {
            return $halCollection;
        }

        $events->trigger(eventName: 'getList.post', target: $this, argv: [
            'collection' => $halCollection,
        ]);

        return $halCollection;
    }

    /**
     * Retrieve HEAD metadata for the entity and/or collection
     *
     * @param mixed $id
     * @return Response|ApiProblem|ApiProblemResponse|HalEntity|HalCollection
     */
    #[Override]
    public function head($id = null): HalEntity|Response|ApiProblem|HalCollection|ApiProblemResponse
    {
        if ($id) {
            return $this->get($id);
        }

        return $this->getList();
    }

    /**
     * Respond to OPTIONS request
     *
     * Uses $options to set the Allow header line and return an empty response.
     *
     * @return Response
     */
    #[Override]
    public function options(): Response
    {
        $e = $this->getEvent();
        /** @mago-var mixed $id */
        $id = $this->getIdentifier(
            routeMatch: Assert::isInstanceOf($e->getRouteMatch(), \Jield\Mvc\Router\RouteMatch::class),
            request: $e->getRequest(),
        );

        $options = $id ? $this->entityHttpMethods : $this->collectionHttpMethods;

        $events = $this->getEventManager();
        $events->trigger(eventName: 'options.pre', target: $this, argv: ['options' => $options]);

        /** @var Response $response */
        $response = $this->getResponse();
        $response->setStatusCode(code: 204);

        $headers = $response->getHeaders();
        $headers->addHeader(header: $this->createAllowHeaderWithAllowedMethods(methods: $options));

        $events->trigger(eventName: 'options.post', target: $this, argv: ['options' => $options]);

        return $response;
    }

    /**
     * Respond to the PATCH method (partial update of existing entity)
     */
    #[Override]
    public function patch($id, $data): HalEntity|Response|ApiProblem|ApiProblemResponse
    {
        $events = $this->getEventManager();
        $events->trigger(eventName: 'patch.pre', target: $this, argv: ['id' => $id, 'data' => $data]);

        try {
            /** @mago-var mixed $entity */
            $entity = $this->getResource()->patch(id: (int) $id, data: Value::arrayOrObject(empty($data) ? [] : $data));
        } catch (Throwable $throwable) {
            return $this->createApiProblemFromException(e: $throwable);
        }

        if ($this->isPreparedResponse(object: $entity)) {
            return $entity;
        }

        $halEntity = $this->createHalEntity(entity: $entity);

        $events->trigger(eventName: 'patch.post', target: $this, argv: [
            'id' => $id,
            'data' => $data,
            'entity' => $halEntity,
            'resource' => $halEntity,
        ]);

        return $halEntity;
    }

    /**
     * Update an existing entity
     *
     * @param mixed $id
     * @param mixed $data
     * @return Response|ApiProblem|ApiProblemResponse|HalEntity
     */
    #[Override]
    public function update($id, $data): HalEntity|Response|ApiProblem|ApiProblemResponse
    {
        $events = $this->getEventManager();
        $events->trigger(eventName: 'update.pre', target: $this, argv: ['id' => $id, 'data' => $data]);

        try {
            /** @mago-var mixed $entity */
            $entity = $this->getResource()->update(id: Value::key($id), data: Value::arrayOrObject($data));
        } catch (Throwable $throwable) {
            return $this->createApiProblemFromException(e: $throwable);
        }

        if ($this->isPreparedResponse(object: $entity)) {
            return $entity;
        }

        $halEntity = $this->createHalEntity(entity: $entity);

        $events->trigger(eventName: 'update.post', target: $this, argv: [
            'id' => $id,
            'data' => $data,
            'entity' => $halEntity,
        ]);

        return $halEntity;
    }

    /**
     * Respond to the PATCH method (partial update of existing entity) on
     * a collection, i.e. create and/or update multiple entities in a collection.
     */
    #[Override]
    public function patchList($data): HalCollection|ApiProblem|Response
    {
        $events = $this->getEventManager();
        $events->trigger(eventName: 'patchList.pre', target: $this, argv: ['data' => $data]);

        try {
            $collection = $this->getResource()->patchList(data: Assert::isArray($data));
        } catch (Throwable $throwable) {
            return $this->createApiProblemFromException(e: $throwable);
        }

        if ($this->isPreparedResponse(object: $collection)) {
            return $collection;
        }

        $halCollection = $this->createHalCollection(collection: $collection);

        $events->trigger(eventName: 'patchList.post', target: $this, argv: [
            'data' => $data,
            'collection' => $halCollection,
        ]);

        return $halCollection;
    }

    /**
     * Update an existing collection of entities
     *
     * @param mixed $data
     * @return HalCollection|ApiProblem|Response
     */
    #[Override]
    public function replaceList($data): HalCollection|ApiProblem|Response
    {
        $events = $this->getEventManager();
        $events->trigger(eventName: 'replaceList.pre', target: $this, argv: ['data' => $data]);

        try {
            /** @mago-var mixed $collection */
            $collection = $this->getResource()->replaceList(data: Assert::isArray($data));
        } catch (InvalidArgumentException $e) {
            return new ApiProblem(status: 400, detail: $e->getMessage());
        } catch (Throwable $e) {
            return $this->createApiProblemFromException(e: $e);
        }

        if ($this->isPreparedResponse(object: $collection)) {
            return $collection;
        }

        $halCollection = $this->createHalCollection(collection: $collection);

        $events->trigger(eventName: 'replaceList.post', target: $this, argv: [
            'data' => $data,
            'collection' => $halCollection,
        ]);

        return $halCollection;
    }

    /**
     * Retrieve the identifier, if any
     *
     * Attempts to see if an identifier was passed in the URI,
     * returning it if found. Otherwise, returns a boolean false.
     *
     * @param RouteMatch $routeMatch
     * @param RequestInterface $request
     * @return mixed
     */
    #[Override]
    protected function getIdentifier($routeMatch, $request): mixed
    {
        $identifier = $this->getIdentifierName();
        /** @mago-var mixed $id */
        $id = $routeMatch->getParam(name: $identifier, default: false);
        if ($id !== null) {
            return $id;
        }

        return false;
    }

    /**
     * Creates an ALLOW header with the provided HTTP methods
     *
     * @param array $methods
     * @return Allow
     */
    protected function createAllowHeaderWithAllowedMethods(array $methods): Allow
    {
        // Need to create an Allow header. It has several defaults, and the only
        // way to start with a clean slate is to retrieve all methods, disallow
        // them all, and then set the ones we want to allow.
        $allow = new Allow();
        $allMethods = $allow->getAllMethods();
        $allow->disallowMethods(disallowedMethods: array_keys(array: $allMethods));
        $allow->allowMethods(allowedMethods: $methods);
        return $allow;
    }

    /**
     * @param Throwable|Exception $e
     * @return ApiProblem
     */
    protected function createApiProblemFromException(Throwable|Exception $e): ApiProblem
    {
        return new ApiProblem(status: $this->getHttpStatusCodeFromException(e: $e), detail: $e);
    }

    /**
     * Ensure we have a valid HTTP status code for an ApiProblem
     *
     * @param Throwable|Exception $e
     * @return int
     */
    protected function getHttpStatusCodeFromException(Throwable|Exception $e): int
    {
        $code = $e->getCode();
        if (!is_int(value: $code) || $code < 100 || $code >= 600) {
            return 500;
        }

        return $code;
    }

    /**
     * Injects the resource with the identity composed in the event, if present
     */
    protected function injectEventIdentityIntoResource(): void
    {
        if (Assert::notNull($this->resource)->getIdentity()) {
            return;
        }

        $event = $this->getEvent();

        /** @mago-var mixed $identity */
        $identity = $event->getParam(name: AuthenticatedIdentity::class);
        if (!$identity) {
            return;
        }

        Assert::notNull($this->resource)->setIdentity(identity: Assert::isInstanceOf(
            $identity,
            IdentityInterface::class,
        ));
    }

    /**
     * Injects the resource with the input filter composed in the event, if present
     */
    protected function injectEventInputFilterIntoResource(): void
    {
        if (Assert::notNull($this->resource)->getInputFilter()) {
            return;
        }

        $event = $this->getEvent();

        /** @mago-var mixed $inputFilter */
        $inputFilter = $event->getParam(name: 'Jield\ApiTools\ContentValidation\InputFilter');
        if (!$inputFilter) {
            return;
        }

        Assert::notNull($this->resource)->setInputFilter(inputFilter: Value::nullableInstance($inputFilter, \Laminas\InputFilter\InputFilterInterface::class));
    }

    protected function injectRequestIntoResourceEventParams(): void
    {
        $request = $this->getRequest();

        $params = Assert::notNull($this->resource)->getEventParams();

        $params['request'] = $request;
        Assert::notNull($this->resource)->setEventParams(params: $params);
    }

    /** @psalm-assert-if-true ApiProblem|Response $object */
    protected function isPreparedResponse(mixed $object): bool
    {
        return $object instanceof ApiProblem || $object instanceof Response;
    }

    protected function createHalCollection(mixed $collection): HalCollection|ApiProblem
    {
        if (!$collection instanceof HalCollection) {
            /** @mago-var mixed $halPlugin */
            $halPlugin = $this->plugin(name: 'Hal');
            /** @mago-var mixed $collection */
            $collection = Value::call($halPlugin, 'createCollection', [$collection, $this->route]);
        }

        return $this->prepareHalCollection(collection: Assert::isInstanceOf($collection, \Jield\ApiTools\Hal\Collection::class));
    }

    /**
     * Prepare a HAL collection with the metadata for the current instance.
     */
    protected function prepareHalCollection(HalCollection $collection): HalCollection|ApiProblem
    {
        if (!$collection->getLinks()->has(relation: 'self')) {
            /** @mago-var mixed $plugin */
            $plugin = $this->plugin(name: 'Hal');
            Value::call($plugin, 'injectSelfLink', [$collection, $this->route]);
        }

        $collection->setCollectionRoute(route: Assert::string($this->route));
        $collection->setRouteIdentifierName(identifier: $this->getRouteIdentifierName());
        $collection->setEntityRoute(route: Assert::string($this->route));
        $collection->setCollectionName(name: $this->collectionName);

        try {
            $collection->setPageSize(size: $this->getPageSize());

            $request = $this->getRequest();
            $page = 1;
            if (method_exists(object_or_class: $request, method: 'getQuery')) {
                $page = (int) $request->getQuery('page', 1);
            }

            $collection->setPage(page: $page);
        } catch (HalInvalidArgumentException $halInvalidArgumentException) {
            return new ApiProblem(status: 400, detail: $halInvalidArgumentException->getMessage());
        }

        return $collection;
    }

    protected function createHalEntity(mixed $entity): HalEntity
    {
        if ($entity instanceof HalEntity && ($entity->getLinks()->has(relation: 'self') || !$entity->getId())) {
            return $entity;
        }

        /** @mago-var mixed $plugin */
        $plugin = $this->plugin(name: 'Hal');

        return Assert::isInstanceOf(Value::call($plugin, 'createEntity', [
            $entity,
            $this->route,
            $this->getRouteIdentifierName(),
        ]), \Jield\ApiTools\Hal\Entity::class);
    }
}
