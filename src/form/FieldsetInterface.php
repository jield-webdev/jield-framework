<?php

declare(strict_types=1);

namespace Jield\Form;

use Countable;
use IteratorAggregate;
use Jield\Form\Exception\ExceptionInterface;
use Laminas\Hydrator\HydratorInterface;
use Traversable;

/** @extends IteratorAggregate<string, ElementInterface> */
interface FieldsetInterface extends
    Countable,
    IteratorAggregate,
    ElementInterface,
    ElementPrepareAwareInterface,
    FormFactoryAwareInterface
{
    /**
     * Add an element or fieldset
     *
     * $flags could contain metadata such as the alias under which to register
     * the element or fieldset, order in which to prioritize it, etc.
     *
     * @param Traversable|array|ElementInterface $elementOrFieldset Typically, only allow objects implementing
     *                                                               ElementInterface; however, keeping it flexible
     *                                                               to allow a factory-based form
     *                                                               implementation as well
     * @throws ExceptionInterface
     */
    public function add(Traversable|ElementInterface|array $elementOrFieldset, array $flags = []): FieldsetInterface;

    /**
     * Does the fieldset have an element/fieldset by the given name?
     */
    public function has(string $elementOrFieldset): bool;

    /**
     * Retrieve a named element or fieldset
     */
    public function get(string $elementOrFieldset): ElementInterface;

    /**
     * Remove a named element or fieldset
     */
    public function remove(string $elementOrFieldset): FieldsetInterface;

    /**
     * Set/change the priority of an element or fieldset
     */
    public function setPriority(string $elementOrFieldset, int $priority): FieldsetInterface;

    /**
     * Retrieve all attached elements
     *
     * Storage is an implementation detail of the concrete class.
     *
     * @return ElementInterface[]
     */
    public function getElements(): array;

    /**
     * Retrieve all attached fieldsets
     *
     * Storage is an implementation detail of the concrete class.
     *
     * @return FieldsetInterface[]
     */
    public function getFieldsets(): array;

    /**
     * Recursively populate value attributes of elements
     */
    public function populateValues(iterable $data): void;

    /**
     * Set the object used by the hydrator
     */
    public function setObject(mixed $object): FieldsetInterface;

    /**
     * Get the object used by the hydrator
     */
    public function getObject(): mixed;

    /**
     * Checks if the object can be set in this fieldset
     */
    public function allowObjectBinding(object $object): bool;

    /**
     * Set the hydrator to use when binding an object to the element
     */
    public function setHydrator(HydratorInterface $hydrator): FieldsetInterface;

    /**
     * Get the hydrator used when binding an object to the element
     */
    public function getHydrator(): ?HydratorInterface;

    /**
     * Bind values to the bound object
     */
    public function bindValues(array $values = [], ?array $validationGroup = null): mixed;

    /**
     * Checks if this fieldset can bind data
     */
    public function allowValueBinding(): bool;
}
