<?php

declare(strict_types=1);

namespace Jield\Authentication\Storage;

use Jield\Authentication\Exception\ExceptionInterface;

interface StorageInterface
{
    /**
     * Returns true if and only if storage is empty
     *
     * @throws ExceptionInterface If it is impossible to determine whether storage is empty.
     * @return bool
     */
    public function isEmpty(): bool;

    /**
     * Returns the contents of storage
     *
     * Behavior is undefined when storage is empty.
     *
     * @throws ExceptionInterface If reading contents from storage is impossible.
     * @return mixed
     */
    public function read(): mixed;

    /**
     * Writes $contents to storage
     *
     * @param  mixed $contents
     * @throws ExceptionInterface If writing $contents to storage is impossible.
     * @return void
     */
    public function write(mixed $contents): void;

    /**
     * Clears contents from storage
     *
     * @throws ExceptionInterface If clearing contents from storage is impossible.
     * @return void
     */
    public function clear(): void;
}
