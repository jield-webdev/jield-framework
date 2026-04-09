<?php

declare(strict_types=1);

namespace Jield\Form;

interface ElementAttributeRemovalInterface
{
    /**
     * Remove a single element attribute
     *
     * @return self
     */
    public function removeAttribute(string $key): ElementAttributeRemovalInterface;

    /**
     * Remove many attributes at once
     *
     * @param list<string> $keys
     * @return self
     */
    public function removeAttributes(array $keys): ElementAttributeRemovalInterface;

    /**
     * Remove all attributes at once
     *
     * @return self
     */
    public function clearAttributes(): ElementAttributeRemovalInterface;
}
