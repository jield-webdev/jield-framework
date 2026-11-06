<?php

declare(strict_types=1);

namespace Jield\ApiTools\Rest;

use ArrayObject;
use Jield\ApiTools\ApiProblem\ApiProblem;
use Jield\ApiTools\ApiProblem\ApiProblemResponse;
use Jield\ApiTools\Hal\Collection as HalCollection;
use Jield\ApiTools\MvcAuth\Identity\IdentityInterface;
use Jield\Mvc\Router\RouteMatch;
use Laminas\EventManager\EventManager;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ResponseCollection;
use Laminas\Http\Response;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\Paginator\Adapter\ArrayAdapter;
use Laminas\Paginator\Paginator;
use Laminas\Stdlib\Parameters;
use Override;

use function array_merge;
use function array_walk;
use function gettype;
use function is_array;
use function is_bool;
use function is_object;
use function method_exists;
use function sprintf;

/**
 * Base resource class
 *
 * Essentially, simply marshals arguments and triggers events; it is useless
 * without listeners to do the actual work.
 */
class Resource implements ResourceInterface
{
    protected ?EventManagerInterface $events = null;

    protected ?IdentityInterface $identity = null;

    protected ?InputFilterInterface $inputFilter = null;

    protected array $params = [];

    protected ?Parameters $queryParams = null;

    protected ?RouteMatch $routeMatch = null;

    #[Override]
    public function setEventParams(array $params): static
    {
        $this->params = $params;
        return $this;
    }

    #[Override]
    public function getEventParams(): array
    {
        return $this->params;
    }

    public function setIdentity(?IdentityInterface $identity = null): static
    {
        $this->identity = $identity;
        return $this;
    }

    public function getIdentity(): ?IdentityInterface
    {
        return $this->identity;
    }

    public function setInputFilter(?InputFilterInterface $inputFilter = null): static
    {
        $this->inputFilter = $inputFilter;
        return $this;
    }

    public function getInputFilter(): ?InputFilterInterface
    {
        return $this->inputFilter;
    }

    public function setQueryParams(?Parameters $params = null): static
    {
        $this->queryParams = $params;
        return $this;
    }

    public function getQueryParams(): ?Parameters
    {
        return $this->queryParams;
    }

    public function setRouteMatch(?RouteMatch $matches = null): static
    {
        $this->routeMatch = $matches;
        return $this;
    }

    public function getRouteMatch(): ?RouteMatch
    {
        return $this->routeMatch;
    }

    #[Override]
    public function setEventParam(string $name, mixed $value): static
    {
        $this->params[$name] = $value;
        return $this;
    }

    #[Override]
    public function getEventParam(mixed $name, mixed $default = null): mixed
    {
        return $this->params[$name] ?? $default;
    }

    #[Override]
    public function setEventManager(EventManagerInterface $eventManager): static
    {
        $eventManager->addIdentifiers(identifiers: [
            static::class,
            self::class,
            ResourceInterface::class,
        ]);
        $this->events = $eventManager;
        return $this;
    }

    /**
     * Retrieve event manager
     *
     * Lazy-instantiates an EM instance if none provided.
     *
     * @return EventManagerInterface
     */
    #[Override]
    public function getEventManager(): EventManagerInterface
    {
        if (!$this->events) {
            $this->setEventManager(eventManager: new EventManager());
        }

        return $this->events;
    }

    /**
     * Create a record in the resource
     *
     * Expects either an array or object representing the item to create. If
     * a non-array, non-object is provided, raises an exception.
     *
     * The value returned by the last listener to the "create" event will be
     * returned as long as it is an array or object; otherwise, the original
     * $data is returned. If you wish to indicate failure to create, raise a
     * Jield\ApiTools\Rest\Exception\CreationException from a listener.
     *
     * @param object|array $data
     * @return array|object
     * @throws Exception\InvalidArgumentException
     */
    #[Override]
    public function create(object|array $data): object|array
    {
        if (is_array(value: $data)) {
            $data = (object) $data;
        }

        

        $results = $this->triggerEvent(name: __FUNCTION__, args: ['data' => $data]);
        /** @var mixed $last */
        $last = $results->last();
        if (!is_array(value: $last) && !is_object(value: $last)) {
            return $data;
        }

        return $last;
    }

    /**
     * Update (replace) an existing item
     *
     * Updates the item indicated by $id, replacing it with the information
     * in $data. $data should be a full representation of the item, and should
     * be an array or object; if otherwise, an exception will be raised.
     *
     * Like create(), the return value of the last executed listener will be
     * returned, as long as it is an array or object; otherwise, $data is
     * returned. If you wish to indicate failure to update, raise a
     * Jield\ApiTools\Rest\Exception\UpdateException.
     *
     * @param int|string $id
     * @param object|array $data
     * @return array|object
     * @throws Exception\InvalidArgumentException
     */
    #[Override]
    public function update(int|string $id, object|array $data): object|array
    {
        if (is_array(value: $data)) {
            $data = (object) $data;
        }

        

        $results = $this->triggerEvent(name: __FUNCTION__, args: [
            'id' => $id,
            'data' => $data,
        ]);
        /** @var mixed $last */
        $last = $results->last();
        if (!is_array(value: $last) && !is_object(value: $last)) {
            return $data;
        }

        return $last;
    }

    /**
     * Update (replace) an existing collection of items
     *
     * Replaces the collection with  the items contained in $data.
     * $data should be a multidimensional array or array of objects; if
     * otherwise, an exception will be raised.
     *
     * Like update(), the return value of the last executed listener will be
     * returned, as long as it is an array or object; otherwise, $data is
     * returned. If you wish to indicate failure to update, raise a
     * Jield\ApiTools\Rest\Exception\UpdateException.
     *
     * @param array $data
     * @return array|object
     * @throws Exception\InvalidArgumentException
     */
    #[Override]
    public function replaceList(array $data): object|array
    {
        array_walk(array: $data, callback: static function ($value, $key) use (&$data): void {
            if (is_array(value: $value)) {
                $data[$key] = (object) $value;
                return;
            }

            if (!is_object(value: $value)) {
                throw new Exception\InvalidArgumentException(
                    message: sprintf(
                        'Data provided to replaceList must contain only arrays or objects; received "%s"',
                        gettype(value: $value),
                    ),
                    code: 400,
                );
            }
        });

        $results = $this->triggerEvent(name: __FUNCTION__, args: ['data' => $data]);
        /** @var mixed $last */
        $last = $results->last();
        if (!is_array(value: $last) && !is_object(value: $last)) {
            return $data;
        }

        return $last;
    }

    /**
     * Partial update of an existing item
     *
     * Update the item indicated by $id, using the information from $data;
     * $data should be merged with the existing item in order to provide a
     * partial update. Additionally, $data should be an array or object; any
     * other value will raise an exception.
     *
     * Like create(), the return value of the last executed listener will be
     * returned, as long as it is an array or object; otherwise, $data is
     * returned. If you wish to indicate failure to update, raise a
     * Jield\ApiTools\Rest\Exception\PatchException.
     *
     * @param int|string $id
     * @param object|array $data
     * @return array|object
     * @throws Exception\InvalidArgumentException
     */
    #[Override]
    public function patch(int|string $id, object|array $data): object|array
    {
        if (is_array(value: $data)) {
            $data = (object) $data;
        }

        

        $results = $this->triggerEvent(name: __FUNCTION__, args: [
            'id' => $id,
            'data' => $data,
        ]);
        /** @var mixed $last */
        $last = $results->last();
        if (!is_array(value: $last) && !is_object(value: $last)) {
            return $data;
        }

        return $last;
    }

    /**
     * Patches the collection with  the items contained in $data.
     * $data should be a multidimensional array or array of objects; if
     * otherwise, an exception will be raised.
     *
     * Like update(), the return value of the last executed listener will be
     * returned, as long as it is an array or object; otherwise, $data is
     * returned.
     *
     * As this method can create and update resources, if you wish to indicate
     * failure to update, raise a PhlyRestfully\Exception\UpdateException and
     * if you wish to indicate a failure to create, raise a
     * PhlyRestfully\Exception\CreationException.
     *
     * @param array $data
     * @return array|object
     * @throws Exception\InvalidArgumentException
     */
    public function patchList(array $data): object|array
    {
        $original = $data;
        array_walk(array: $data, callback: static function ($value, $key) use (&$data): void {
            if (is_array(value: $value)) {
                $data[$key] = new ArrayObject( $value);
                return;
            }

            if (!is_object(value: $value)) {
                throw new Exception\InvalidArgumentException(
                    message: sprintf(
                        'Data provided to patchList must contain only arrays or objects; received "%s"',
                        gettype(value: $value),
                    ),
                    code: 400,
                );
            }
        });

        $data = new ArrayObject( $data);
        $results = $this->triggerEvent(name: __FUNCTION__, args: ['data' => $data]);
        /** @var mixed $last */
        $last = $results->last();
        if (!is_array(value: $last) && !is_object(value: $last)) {
            return $original;
        }

        return $last;
    }

    /**
     * Delete an existing item
     *
     * Use to delete the item indicated by $id. The value returned by the last
     * listener will be used, as long as it is a boolean; otherwise, a boolean
     * false will be returned, indicating failure to delete.
     */
    #[Override]
    public function delete(int|string $id): Response|bool|ApiProblem|ApiProblemResponse
    {
        $results = $this->triggerEvent(name: __FUNCTION__, args: ['id' => $id]);
        /** @var mixed $last */
        $last = $results->last();
        if (
            !is_bool(value: $last)
            && !$last instanceof ApiProblem
            && !$last instanceof ApiProblemResponse
            && !$last instanceof Response
        ) {
            return false;
        }

        return $last;
    }

    /**
     * Delete an existing collection of records
     *
     * @param array $data
     * @return bool|Response
     */
    #[Override]
    public function deleteList(array $data): mixed
    {
        $results = $this->triggerEvent(name: __FUNCTION__, args: ['data' => $data]);
        /** @var mixed $last */
        $last = $results->last();
        if (!is_bool(value: $last) && !$last instanceof Response) {
            return false;
        }

        return $last;
    }

    /**
     * Fetch an existing item
     *
     * Retrieve an existing item indicated by $id. The value of the last
     * listener will be returned, as long as it is an array or object;
     * otherwise, a boolean false value will be returned, indicating a
     * lookup failure.
     *
     * @param int|string $id
     * @return false|array|object
     */
    #[Override]
    public function fetch(int|string $id): object|false|array
    {
        $results = $this->triggerEvent(name: __FUNCTION__, args: ['id' => $id]);
        /** @var mixed $last */
        $last = $results->last();
        if (!is_array(value: $last) && !is_object(value: $last)) {
            return false;
        }

        return $last;
    }

    /**
     * Fetch a collection of items
     *
     * Use to retrieve a collection of items. The value of the last
     * listener will be returned, as long as it is an array or Traversable;
     * otherwise, an empty array will be returned.
     *
     * The recommendation is to return a \Laminas\Paginator\Paginator instance,
     * which will allow performing paginated sets, and thus allow the view
     * layer to select the current page based on the query string or route.
     */
    #[Override]
    public function fetchAll(...$params): object|false|array
    {
        $results = $this->triggerEvent(name: __FUNCTION__, args: $params);
        /** @var mixed $last */
        $last = $results->last();
        if (
            !is_array(value: $last)
            && !$last instanceof HalCollection
            && !$last instanceof ApiProblem
            && !$last instanceof ApiProblemResponse
            && !is_object(value: $last)
        ) {
            return new Paginator(new ArrayAdapter(array: []));
        }

        return $last;
    }

    /**
     * @param string $name
     * @param array $args
     * @return ResponseCollection
     */
    protected function triggerEvent(string $name, array $args): ResponseCollection
    {
        return $this->getEventManager()->triggerEventUntil(
            callback: static fn($result) => (
                $result instanceof ApiProblem
                || $result instanceof ApiProblemResponse
                || $result instanceof Response
            ),
            event: $this->prepareEvent(name: $name, args: $args),
        );
    }

    /**
     * Prepare event parameters
     *
     * Merges any event parameters set in the resources with arguments passed
     * to a resource method, and passes them to the `prepareArgs` method of the
     * event manager.
     *
     * If an input filter is composed, this, too, is injected into the event.
     *
     * @param string $name
     * @param array $args
     * @return ResourceEvent
     */
    protected function prepareEvent(string $name, array $args): ResourceEvent
    {
        $event = new ResourceEvent(name: $name, target: $this, params: $this->prepareEventParams(args: $args));
        $event->setIdentity(identity: $this->getIdentity());
        $event->setInputFilter(inputFilter: $this->getInputFilter());
        $event->setQueryParams(params: $this->getQueryParams());
        $event->setRouteMatch(matches: $this->getRouteMatch());

        return $event;
    }

    /**
     * Prepare event parameters
     *
     * Ensures event parameters are created as an array object, allowing them to be modified
     * by listeners and retrieved.
     *
     * @param array $args
     * @return ArrayObject
     */
    protected function prepareEventParams(array $args): ArrayObject
    {
        $defaultParams = $this->getEventParams();
        $params = array_merge($defaultParams, $args);
        if ($params === []) {
            return new ArrayObject();
        }

        return new ArrayObject($params);
    }
}
