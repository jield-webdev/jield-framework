<?php

namespace Laminas\Stdlib;

class ArrayUtils
{
    /**
     * PHP arrays always have integer or string keys, including when built from an iterator.
     * @template TValue
     * @param iterable<mixed, TValue> $iterator
     * @return array<array-key, TValue>
     */
    public static function iteratorToArray(iterable $iterator, bool $recursive = true): array {}
}
