<?php

declare(strict_types=1);

namespace Jield\Authentication\Storage;

use Jield\Stdlib\PriorityQueue;
use Override;

class Chain implements StorageInterface
{
    /**
     * Contains all storage that this authentication method uses. A storage
     * placed in the priority queue with a higher priority is always used
     * before using a storage with a lower priority.
     *
     * @var PriorityQueue<StorageInterface, int>
     */
    protected PriorityQueue $storageChain;

    /**
     * Initializes the priority queue.
     */
    public function __construct()
    {
        /** @psalm-var PriorityQueue<StorageInterface, int> */
        $this->storageChain = new PriorityQueue();
    }

    /**
     * @param int $priority
     * @return void
     */
    public function add(StorageInterface $storage, int $priority = 1): void
    {
        $this->storageChain->insert(data: $storage, priority: $priority);
    }

    /**
     * Loop over the queue of storage until a storage is found that is non-empty. If such
     * storage is not found, then this chain storage itself is empty.
     *
     * In case a non-empty storage is found then this chain storage is also non-empty. Report
     * that, but also make sure that all storage with higher priority that are empty
     * are filled.
     *
     * @see StorageInterface::isEmpty()
     *
     * @return bool
     */
    #[Override]
    public function isEmpty(): bool
    {
        $storageWithHigherPriority = [];

        // Loop invariant: $storageWithHigherPriority contains all storage with higher priority
        // than the current one.
        foreach ($this->storageChain as $storage) {
            if ($storage->isEmpty()) {
                $storageWithHigherPriority[] = $storage;
                continue;
            }

            $storageValue = $storage->read();
            foreach ($storageWithHigherPriority as $higherPriorityStorage) {
                $higherPriorityStorage->write($storageValue);
            }

            return false;
        }

        return true;
    }

    /**
     * If the chain is non-empty then the storage with the top priority is guaranteed to be
     * filled. Return its value.
     *
     * @see StorageInterface::read()
     *
     * @return mixed
     */
    #[Override]
    public function read(): mixed
    {
        return $this->storageChain->top()->read();
    }

    /**
     * Write the new $contents to all storage in the chain.
     *
     * @param mixed $contents
     * @return void
     *@see StorageInterface::write()
     *
     */
    #[Override]
    public function write(mixed $contents): void
    {
        foreach ($this->storageChain as $storage) {
            $storage->write(contents: $contents);
        }
    }

    /**
     * Clear all storage in the chain.
     *
     * @see StorageInterface::clear()
     */
    #[Override]
    public function clear(): void
    {
        foreach ($this->storageChain as $storage) {
            $storage->clear();
        }
    }
}
