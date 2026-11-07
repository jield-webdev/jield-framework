<?php

declare(strict_types=1);

namespace Jield\ApiTools\Rest;

use Jield\ApiTools\ApiProblem\ApiProblem;
use Jield\ApiTools\MvcAuth\Identity\IdentityInterface;
use Jield\Stdlib\Value;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;
use Laminas\EventManager\ListenerAggregateTrait;
use Laminas\Http\Response;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\Paginator\Paginator;
use Laminas\Stdlib\Parameters;
use Override;
use Webmozart\Assert\Assert;

use function sprintf;

abstract class AbstractResourceListener implements ListenerAggregateInterface
{
    use ListenerAggregateTrait;

    protected ResourceEvent $event;

    /**
     * The entity_class config for the calling controller api-tools-rest config
     */
    protected string $entityClass;

    /**
     * The collection_class config for the calling controller api-tools-rest config
     */
    protected string $collectionClass;

    /**
     * Current identity, if discovered in the resource event.
     */
    protected ?IdentityInterface $identity = null;

    /**
     * Input filter, if discovered in the resource event.
     */
    protected ?InputFilterInterface $inputFilter = null;

    /**
     * Set the entity_class for the controller config calling this resource
     */
    public function setEntityClass(string $className): static
    {
        $this->entityClass = $className;
        return $this;
    }

    public function setCollectionClass(string $className): static
    {
        $this->collectionClass = $className;
        return $this;
    }

    /**
     * Retrieve the current resource event, if any
     */
    public function getEvent(): ResourceEvent
    {
        return $this->event;
    }

    /**
     * Retrieve the identity, if any
     *
     * Proxies to the resource event to find the identity, if not already
     * composed, and composes it.
     */
    public function getIdentity(): ?IdentityInterface
    {
        if ($this->identity) {
            return $this->identity;
        }

        $event = $this->getEvent();

        $this->identity = $event->getIdentity();
        return $this->identity;
    }

    /**
     * Retrieve the input filter, if any
     *
     * Proxies to the resource event to find the input filter, if not already
     * composed, and composes it.
     */
    public function getInputFilter(): ?InputFilterInterface
    {
        if ($this->inputFilter) {
            return $this->inputFilter;
        }

        $event = $this->getEvent();

        $this->inputFilter = $event->getInputFilter();
        return $this->inputFilter;
    }

    /**
     * Attach listeners for all Resource events
     *
     * @param int $priority
     */
    #[Override]
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(eventName: 'create', listener: $this->dispatch(...));
        $this->listeners[] = $events->attach(eventName: 'delete', listener: $this->dispatch(...));
        $this->listeners[] = $events->attach(eventName: 'deleteList', listener: $this->dispatch(...));
        $this->listeners[] = $events->attach(eventName: 'fetch', listener: $this->dispatch(...));
        $this->listeners[] = $events->attach(eventName: 'fetchAll', listener: $this->dispatch(...));
        $this->listeners[] = $events->attach(eventName: 'patch', listener: $this->dispatch(...));
        $this->listeners[] = $events->attach(eventName: 'patchList', listener: $this->dispatch(...));
        $this->listeners[] = $events->attach(eventName: 'replaceList', listener: $this->dispatch(...));
        $this->listeners[] = $events->attach(eventName: 'update', listener: $this->dispatch(...));
    }

    /**
     * Dispatch an incoming event to the appropriate method
     *
     * Marshals arguments from the event parameters.
     *
     * @throws Exception\RuntimeException
     */
    public function dispatch(ResourceEvent $event): mixed
    {
        $this->event = $event;
        switch ($event->getName()) {
            case 'create':
                /** @mago-var mixed $data */
                $data = $event->getParam(name: 'data', default: []);
                return $this->create(data: new Parameters(Value::toArray($data)));
            case 'delete':
                $id = (int) $event->getParam(name: 'id');
                return $this->delete(id: $id);
            case 'deleteList':
                /** @mago-var mixed $data */
                $data = $event->getParam(name: 'data', default: []);
                return $this->deleteList(data: new Parameters(Value::toArray($data)));
            case 'fetch':
                /** @mago-var mixed $id */
                $id = $event->getParam(name: 'id');
                return $this->fetch(id: Assert::string($id));
            case 'fetchAll':
                $queryParams = $event->getQueryParams() ?: [];
                return $this->fetchAll(params: Assert::isInstanceOf($queryParams, \Laminas\Stdlib\Parameters::class));
            case 'patch':
                $id = (int) $event->getParam(name: 'id');
                /** @mago-var mixed $data */
                $data = $event->getParam(name: 'data', default: []);
                return $this->patch(id: $id, data: new Parameters(Value::toArray($data)));
            case 'patchList':
                /** @mago-var mixed $data */
                $data = $event->getParam(name: 'data', default: []);
                return $this->patchList(data: new Parameters(Value::toArray($data)));
            case 'replaceList':
                /** @mago-var mixed $data */
                $data = $event->getParam(name: 'data', default: []);
                return $this->replaceList(data: new Parameters(Value::toArray($data)));
            case 'update':
                $id = (int) $event->getParam(name: 'id');
                /** @mago-var mixed $data */
                $data = $event->getParam(name: 'data', default: []);
                return $this->update(id: $id, data: new Parameters(Value::toArray($data)));
            default:
                throw new Exception\RuntimeException(message: sprintf(
                    '%s has not been setup to handle the event "%s"',
                    __METHOD__,
                    $event->getName(),
                ));
        }
    }

    /**
     * Create a resource
     */
    public function create(Parameters $data): array|ApiProblem
    {
        return new ApiProblem(status: 405, detail: 'The POST method has not been defined');
    }

    /**
     * Delete a resource
     */
    public function delete(int $id): ApiProblem|Response
    {
        return new ApiProblem(status: 405, detail: 'The DELETE method has not been defined for individual resources');
    }

    /**
     * Delete a collection, or members of a collection
     */
    public function deleteList(Parameters $data): ApiProblem|Response
    {
        return new ApiProblem(status: 405, detail: 'The DELETE method has not been defined for collections');
    }

    /**
     * Fetch a resource
     */
    public function fetch(string $id): array|ApiProblem
    {
        return new ApiProblem(status: 405, detail: 'The GET method has not been defined for individual resources');
    }

    /**
     * Fetch all or a subset of resources
     */
    public function fetchAll(Parameters $params): array|ApiProblem|Paginator
    {
        return new ApiProblem(status: 405, detail: 'The GET method has not been defined for collections');
    }

    /**
     * Patch (partial in-place update) a resource
     */
    public function patch(int $id, Parameters $data): array|ApiProblem
    {
        return new ApiProblem(status: 405, detail: 'The PATCH method has not been defined for individual resources');
    }

    /**
     * Patch (partial in-place update) a collection or members of a collection
     */
    public function patchList(Parameters $data): array|ApiProblem
    {
        return new ApiProblem(status: 405, detail: 'The PATCH method has not been defined for collections');
    }

    /**
     * Replace a collection or members of a collection
     */
    public function replaceList(Parameters $data): array|ApiProblem
    {
        return new ApiProblem(status: 405, detail: 'The PUT method has not been defined for collections');
    }

    /**
     * Update a resource
     */
    public function update(int $id, Parameters $data): array|ApiProblem
    {
        return new ApiProblem(status: 405, detail: 'The PUT method has not been defined for individual resources');
    }
}
