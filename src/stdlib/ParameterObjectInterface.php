<?php

declare(strict_types=1);

namespace Jield\Stdlib;

/**
 * @template TKey of string
 * @template TValue
 */
interface ParameterObjectInterface
{
    /**
     * @param TKey $key
     * @param TValue|null $value
     * @return void
     */
    public function __set(string $key, mixed $value);

    /**
     * @param TKey $key
     * @return TValue
     */
    public function __get(string $key);

    /**
     * @param TKey $key
     * @return bool
     */
    public function __isset(string $key);

    /**
     * @param TKey $key
     * @return void
     */
    public function __unset(string $key);
}
