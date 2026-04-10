<?php

declare(strict_types=1);

namespace Jield\ApiTools\Rest;

use Laminas\EventManager\EventManagerAwareInterface;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\Router\RouteMatch;
use Laminas\Stdlib\Parameters;
use Jield\ApiTools\MvcAuth\Identity\IdentityInterface;

/**
 * Interface describing operations for a given resource.
 */
interface ResourceInterface extends EventManagerAwareInterface
{
    /**
     * Set the event parameters
     */
    public function setEventParams(array $params): ResourceInterface;

    /**
     * Get the event parameters
     */
    public function getEventParams(): array;

    /**
     * @param string $name
     */
    public function setEventParam(string $name, mixed $value): mixed;

    public function getEventParam(mixed $name, mixed $default = null): mixed;

    public function setIdentity(?IdentityInterface $identity = null): static;

    public function getIdentity(): ?IdentityInterface;

    public function setInputFilter(?InputFilterInterface $inputFilter = null): static;

    public function getInputFilter(): ?InputFilterInterface;

    public function setQueryParams(?Parameters $params = null): static;

    public function getQueryParams(): ?Parameters;

    public function setRouteMatch(?RouteMatch $matches = null): static;

    public function getRouteMatch(): ?RouteMatch;

    /**
     * Create a record in the resource
     */
    public function create(Parameters $data): mixed;

    /**
     * Update (replace) an existing record
     */
    public function update(int|string $id, object|array $data): mixed;

    /**
     * Update (replace) an existing collection of records
     */
    public function replaceList(array $data): mixed;

    /**
     * Partial update of an existing record
     */
    public function patch(int|string $id, object|array $data): mixed;

    /**
     * Partial update of an existing collection of records
     */
    public function patchList(array $data): object|array;

    /**
     * Delete an existing record
     */
    public function delete(int|string $id): mixed;

    /**
     * Delete an existing collection of records
     */
    public function deleteList(array $data): mixed;

    /**
     * Fetch an existing record
     */
    public function fetch(int|string $id): mixed;

    /**
     * Fetch a collection of records
     */
    public function fetchAll(): mixed;
}
