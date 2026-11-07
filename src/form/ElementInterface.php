<?php

declare(strict_types=1);

namespace Jield\Form;

use Jield\Form\Exception\ExceptionInterface;

// Preserve the existing public API and the state and operations that implement it.
// @mago-expect lint:too-many-methods(1)
interface ElementInterface
{
    /**
     * Set the name of this element
     *
     * In most cases, this will proxy to the attributes for storage, but is
     * present to indicate that elements are generally named.
     */
    public function setName(string $name): ElementInterface;

    /**
     * Retrieve the element name
     */
    public function getName(): ?string;

    /**
     * Set options for an element
     */
    public function setOptions(iterable $options): ElementInterface;

    /**
     * Set a single option for an element
     */
    public function setOption(string $key, mixed $value): ElementInterface;

    /**
     * get the defined options
     */
    public function getOptions(): array;

    /**
     * return the specified option
     */
    public function getOption(string $option): mixed;

    /**
     * Set a single element attribute
     *
     * @param scalar|null $value
     */
    public function setAttribute(string $key, mixed $value): ElementInterface;

    /**
     * Retrieve a single element attribute
     *
     * @return scalar|null
     */
    public function getAttribute(string $key): mixed;

    /**
     * Return true if a specific attribute is set
     */
    public function hasAttribute(string $key): bool;

    /**
     * Set many attributes at once
     *
     * Implementation will decide if this will overwrite or merge.
     *
     * @param iterable<string, scalar|null> $arrayOrTraversable
     */
    public function setAttributes(iterable $arrayOrTraversable): ElementInterface;

    /**
     * Retrieve all attributes at once
     *
     * @return array<string, scalar|null>
     */
    public function getAttributes(): array;

    /**
     * Set the value of the element
     */
    public function setValue(mixed $value): ElementInterface;

    /**
     * Retrieve the element value
     */
    public function getValue(): mixed;

    /**
     * Set the label (if any) used for this element
     */
    public function setLabel(?string $label): ElementInterface;

    /**
     * Retrieve the label (if any) used for this element
     */
    public function getLabel(): ?string;

    /**
     * Set a list of messages to report when validation fails
     */
    public function setMessages(iterable $messages): ElementInterface;

    /**
     * Get validation error messages, if any
     *
     * Returns a list of validation failure messages, if any.
     *
     * @throws ExceptionInterface
     */
    public function getMessages(): array;
}
