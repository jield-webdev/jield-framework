<?php

declare(strict_types=1);

namespace Jield\Stdlib;

use ArrayObject as PhpArrayObject;
use ReturnTypeWillChange;

use function http_build_query;
use function parse_str;

/**
 * @template TKey of array-key
 * @template TValue
 * @template-extends PhpArrayObject<TKey, TValue>
 * @template-implements ParametersInterface<TKey, TValue>
 */
class Parameters extends PhpArrayObject implements ParametersInterface
{
    /**
     * Constructor
     *
     * Enforces that we have an array, and enforces parameter access to array
     * elements.
     *
     * @param array<TKey, TValue>|null $values
     */
    public function __construct(?array $values = null)
    {
        if (null === $values) {
            $values = [];
        }
        parent::__construct(array: $values, flags: ArrayObject::ARRAY_AS_PROPS);
    }

    /**
     * Populate from native PHP array
     *
     * @param array<TKey, TValue> $values
     * @return mixed
     */
    public function fromArray(array $values): mixed
    {
        $this->exchangeArray(array: $values);
    }

    /**
     * Populate from query string
     *
     * @param string $string
     * @return mixed
     */
    public function fromString(string $string): mixed
    {
        $array = [];
        parse_str(string: $string, result: $array);
        $this->fromArray(values: $array);
    }

    /**
     * Serialize to native PHP array
     *
     * @return array<TKey, TValue>
     */
    public function toArray(): array
    {
        return $this->getArrayCopy();
    }

    /**
     * Serialize to query string
     *
     * @return string
     */
    public function toString(): string
    {
        return http_build_query(data: $this->toArray());
    }

    /**
     * Retrieve by key
     *
     * Returns null if the key does not exist.
     *
     * @param  TKey $name
     * @return TValue|null
     */
    #[ReturnTypeWillChange]
    public function offsetGet($name): null
    {
        if ($this->offsetExists(key: $name)) {
            return parent::offsetGet(key: $name);
        }

        return null;
    }

    /**
     * @template TDefault
     * @param TKey $name
     * @param null $default optional default value
     * @return TValue|TDefault|null
     */
    public function get($name, null $default = null): null
    {
        if ($this->offsetExists(key: $name)) {
            return parent::offsetGet(key: $name);
        }
        return $default;
    }

    /**
     * @param TKey   $name
     * @param TValue $value
     * @return $this
     */
    public function set($name, $value): static
    {
        $this[$name] = $value;
        return $this;
    }
}
