<?php

declare(strict_types=1);

namespace Jield\Stdlib;

interface ArraySerializableInterface
{
    /**
     * Exchange internal values from provided array
     *
     * @return void
     */
    public function exchangeArray(array $array): void;

    /**
     * Return an array representation of the object
     *
     * @return array
     */
    public function getArrayCopy(): array;
}
