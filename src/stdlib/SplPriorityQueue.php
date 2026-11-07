<?php

declare(strict_types=1);

namespace Jield\Stdlib;

use Serializable;
use UnexpectedValueException;

use function array_key_exists;
use function count;
use function get_debug_type;
use function is_array;
use function min;
use function serialize;
use function sprintf;
use function unserialize;

use const PHP_INT_MAX;

/**
 * Serializable version of SplPriorityQueue
 *
 * Also, provides predictable heap order for datums added with the same priority
 * (i.e., they will be emitted in the same order they are enqueued).
 *
 * @template TValue
 * @template TPriority of int
 * @psalm-type InternalPriority = array{0: mixed, 1: int}
 * @extends \SplPriorityQueue<InternalPriority, TValue>
 */
class SplPriorityQueue extends \SplPriorityQueue implements Serializable
{
    /** Seed used to ensure queue order for items of the same priority */
    private int $serial = PHP_INT_MAX;

    /**
     * Insert a value with a given priority
     *
     * Utilizes the serial counter to ensure that values of equal priority are
     * emitted in the same order in which they are inserted.
     *
     * @param TValue $value
     * @param TPriority|InternalPriority $priority
     */
    public function insert(mixed $value, mixed $priority): true
    {
        if (!is_array($priority)) {
            $priority = [$priority, $this->serial--];
        }

        parent::insert($value, $priority);

        return true;
    }

    /**
     * Serialize to an array
     *
     * Array will be priority => data pairs
     *
     * @return list<TValue>|list<InternalPriority>|list<array{data: TValue, priority: InternalPriority}>
     */
    public function toArray(): array
    {
        $array = [];
        foreach (clone $this as $item) {
            $array[] = $item;
        }
        return $array;
    }

    public function serialize(): string
    {
        return serialize($this->__serialize());
    }

    /**
     * Magic method used for serializing of an instance.
     *
     * @return list<array{data: TValue, priority: InternalPriority}>
     */
    public function __serialize(): array
    {
        $clone = clone $this;
        $clone->setExtractFlags(\SplPriorityQueue::EXTR_BOTH);

        $data = [];
        // EXTR_BOTH changes the iterator's item type along with its extraction mode.
        /** @mago-var array{data: TValue, priority: InternalPriority} $item */
        foreach ($clone as $item) {
            $data[] = $item;
        }
        return $data;
    }

    /**
     * @throws UnexpectedValueException
     */
    public function unserialize(string $data): void
    {
        /** @mago-var mixed $toUnserialize */
        $toUnserialize = unserialize($data);
        if (!is_array($toUnserialize)) {
            throw new UnexpectedValueException(sprintf(
                'Cannot deserialize %s instance; corrupt serialization data',
                self::class,
            ));
        }

        $this->__unserialize($toUnserialize);
    }

    /**
     * Magic method used to rebuild an instance.
     *
     * @param array<array-key, mixed> $data Untrusted serialized queue contents.
     * @throws UnexpectedValueException
     */
    public function __unserialize(array $data): void
    {
        /** @psalm-var non-empty-list<int> $serials */
        $serials = [
            PHP_INT_MAX,
        ];

        /** @mago-var mixed $item */
        foreach ($data as $item) {
            if (!is_array($item)) {
                throw new UnexpectedValueException(sprintf(
                    'Cannot deserialize %s instance: corrupt item; expected array, received %s',
                    self::class,
                    get_debug_type($item),
                ));
            }

            if (!array_key_exists('data', $item)) {
                throw new UnexpectedValueException(sprintf(
                    'Cannot deserialize %s instance: corrupt item; missing "data" element',
                    self::class,
                ));
            }

            if (
                !isset($item['priority'])
                || !is_array($item['priority'])
                || count($item['priority']) !== 2
                || !array_key_exists(0, $item['priority'])
                || !isset($item['priority'][1])
                || !is_int($item['priority'][1])
            ) {
                throw new UnexpectedValueException(sprintf(
                    'Cannot deserialize %s instance: corrupt item; missing or invalid "priority" element',
                    self::class,
                ));
            }

            $serials[] = $item['priority'][1];

            /** @var TValue $value */
            $value = $item['data'];
            $this->insert($value, [$item['priority'][0], $item['priority'][1]]);
        }

        $this->serial = min($serials) - 1;
    }
}
