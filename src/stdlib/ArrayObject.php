<?php

declare(strict_types=1);

namespace Jield\Stdlib;

use AllowDynamicProperties;
use ArrayAccess;
use ArrayIterator;
use Countable;
use Iterator;
use IteratorAggregate;
use ReturnTypeWillChange;
use Serializable;
use UnexpectedValueException;

use function array_key_exists;
use function array_keys;
use function asort;
use function class_exists;
use function count;
use function get_debug_type;
use function get_object_vars;
use function gettype;
use function in_array;
use function is_array;
use function is_callable;
use function is_object;
use function is_string;
use function ksort;
use function natcasesort;
use function natsort;
use function serialize;
use function sprintf;
use function str_starts_with;
use function trigger_error;
use function uasort;
use function uksort;
use function unserialize;

use const E_USER_DEPRECATED;

/**
 * Custom framework ArrayObject implementation
 *
 * Extends version-specific "abstract" implementation.
 *
 * @template TKey of array-key
 * @template TValue
 * @template-implements IteratorAggregate<TKey, TValue>
 * @template-implements ArrayAccess<TKey, TValue>
 * @psalm-no-seal-properties
 */
#[AllowDynamicProperties]
class ArrayObject implements IteratorAggregate, ArrayAccess, Serializable, Countable
{
    /**
     * Properties of the object have their normal functionality
     * when accessed as list (var_dump, foreach, etc.).
     */
    public const int STD_PROP_LIST = 1;

    /**
     * Entries can be accessed as properties (read and write).
     */
    public const int ARRAY_AS_PROPS = 2;

    /** @var array<TKey, TValue> */
    protected array|object $storage;

    /** @var self::STD_PROP_LIST|self::ARRAY_AS_PROPS */
    protected int $flag;

    /** @var class-string<Iterator> */
    protected string $iteratorClass;

    /** @var list<string> */
    protected array $protectedProperties;

    /**
     * @param object|array<TKey, TValue> $input Object values must act like ArrayAccess
     * @param self::STD_PROP_LIST|self::ARRAY_AS_PROPS $flags
     * @param class-string<Iterator> $iteratorClass
     */
    public function __construct(object|array $input = [], int $flags = self::STD_PROP_LIST, string $iteratorClass = ArrayIterator::class)
    {
        $this->setFlags(flags: $flags);
        $this->storage = $input;
        $this->setIteratorClass(class: $iteratorClass);
        $this->protectedProperties = array_keys(array: get_object_vars(object: $this));
    }

    /**
     * Returns whether the requested key exists
     *
     * @param TKey $key
     * @return bool
     */
    public function __isset(mixed $key)
    {
        if ($this->flag === self::ARRAY_AS_PROPS) {
            return $this->offsetExists(key: $key);
        }

        if (in_array(needle: $key, haystack: $this->protectedProperties)) {
            throw new Exception\InvalidArgumentException(message: "$key is a protected property, use a different key");
        }

        return isset($this->$key);
    }

    /**
     * Sets the value at the specified key to value
     *
     * @param TKey $key
     * @param TValue $value
     * @return void
     */
    public function __set(mixed $key, mixed $value)
    {
        if ($this->flag === self::ARRAY_AS_PROPS) {
            $this->offsetSet(offset: $key, value: $value);
            return;
        }

        if (in_array(needle: $key, haystack: $this->protectedProperties)) {
            throw new Exception\InvalidArgumentException(message: "$key is a protected property, use a different key");
        }

        $this->$key = $value;
    }

    /**
     * Unsets the value at the specified key
     *
     * @param TKey $key
     * @return void
     */
    public function __unset(mixed $key)
    {
        if ($this->flag === self::ARRAY_AS_PROPS) {
            $this->offsetUnset(offset: $key);
            return;
        }

        if (in_array(needle: $key, haystack: $this->protectedProperties)) {
            throw new Exception\InvalidArgumentException(message: "$key is a protected property, use a different key");
        }

        unset($this->$key);
    }

    /**
     * Returns the value at the specified key by reference
     *
     * @param TKey $key
     * @return TValue|null
     */
    public function &__get(mixed $key)
    {
        if ($this->flag === self::ARRAY_AS_PROPS) {
            $ret = &$this->offsetGet(key: $key);

            return $ret;
        }

        if (in_array(needle: $key, haystack: $this->protectedProperties, strict: true)) {
            throw new Exception\InvalidArgumentException(message: "$key is a protected property, use a different key");
        }

        return $this->$key;
    }

    /**
     * Appends the value
     *
     * @param TValue $value
     * @return void
     */
    public function append(mixed $value): void
    {
        $this->storage[] = $value;
    }

    /**
     * Sort the entries by value
     *
     * @return void
     */
    public function asort(): void
    {
        asort(array: $this->storage);
    }

    /**
     * Get the number of public properties in the ArrayObject
     *
     * @return positive-int|0
     */
    #[ReturnTypeWillChange]
    public function count(): int
    {
        return count(value: $this->storage);
    }

    /**
     * Exchange the array for another one.
     *
     * Passing non-array values is deprecated and the next major version will add an array type hint
     *
     * @param object|ArrayIterator<TKey, TValue>|ArrayObject<TKey, TValue>|array<TKey, TValue> $data
     * @return array<TKey, TValue>
     */
    public function exchangeArray(ArrayObject|object|array|ArrayIterator $data): object|array
    {
        if (! is_array(value: $data) && ! is_object(value: $data)) {
            throw new Exception\InvalidArgumentException(
                message: 'Passed variable is not an array or object, using empty array instead'
            );
        }

        if (! is_array(value: $data)) {
            trigger_error(
                message: sprintf(
                    'Passing a non-array value to "%s" is deprecated and will be removed in the next major version',
                    __METHOD__,
                ),
                error_level: E_USER_DEPRECATED,
            );
        }

        if ($data instanceof self || $data instanceof \ArrayObject) {
            $data = $data->getArrayCopy();
        }
        if (! is_array(value: $data)) {
            $data = (array) $data;
        }

        $storage = $this->storage;

        $this->storage = $data;

        return $storage;
    }

    /**
     * Creates a copy of the ArrayObject.
     *
     * @return array<TKey, TValue>
     */
    public function getArrayCopy(): object|array
    {
        return $this->storage;
    }

    /**
     * Gets the behavior flags.
     *
     * @return self::STD_PROP_LIST|self::ARRAY_AS_PROPS
     */
    public function getFlags(): int
    {
        return $this->flag;
    }

    /**
     * Create a new iterator from an ArrayObject instance
     *
     * @return Iterator<TKey, TValue>
     */
    #[ReturnTypeWillChange]
    public function getIterator(): Iterator
    {
        $class = $this->iteratorClass;

        return new $class($this->storage);
    }

    /**
     * Gets the iterator classname for the ArrayObject.
     *
     * @return class-string<Iterator>
     */
    public function getIteratorClass(): string
    {
        return $this->iteratorClass;
    }

    /**
     * Sort the entries by key
     *
     * @return void
     */
    public function ksort(): void
    {
        ksort(array: $this->storage);
    }

    /**
     * Sort an array using a case insensitive "natural order" algorithm
     *
     * @return void
     */
    public function natcasesort(): void
    {
        natcasesort(array: $this->storage);
    }

    /**
     * Sort entries using a "natural order" algorithm
     *
     * @return void
     */
    public function natsort(): void
    {
        natsort(array: $this->storage);
    }

    /**
     * Returns whether the requested key exists
     *
     * @param TKey $key
     * @return bool
     */
    #[ReturnTypeWillChange]
    public function offsetExists(mixed $key): bool
    {
        return isset($this->storage[$key]);
    }

    /**
     * {@inheritDoc}
     *
     * @param TKey $key
     * @return TValue|null
     */
    #[ReturnTypeWillChange]
    public function &offsetGet(mixed $key): null
    {
        $ret = null;
        if (! $this->offsetExists(key: $key)) {
            return $ret;
        }
        $ret = &$this->storage[$key];

        return $ret;
    }

    /**
     * Sets the value at the specified key to value
     *
     * @param TKey $offset
     * @param TValue $value
     * @return void
     */
    #[ReturnTypeWillChange]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->storage[$offset] = $value;
    }

    /**
     * Unsets the value at the specified key
     *
     * @param TKey $offset
     * @return void
     */
    #[ReturnTypeWillChange]
    public function offsetUnset(mixed $offset): void
    {
        if ($this->offsetExists(key: $offset)) {
            unset($this->storage[$offset]);
        }
    }

    /**
     * Serialize an ArrayObject
     *
     * @return string
     */
    public function serialize(): string
    {
        return serialize(value: $this->__serialize());
    }

    /**
     * Magic method used for serializing of an instance.
     *
     * @return array<string, mixed>
     */
    public function __serialize()
    {
        return get_object_vars(object: $this);
    }

    /**
     * Sets the behavior flags
     *
     * @param self::STD_PROP_LIST|self::ARRAY_AS_PROPS $flags
     * @return void
     */
    public function setFlags(int $flags): void
    {
        $this->flag = $flags;
    }

    /**
     * Sets the iterator classname for the ArrayObject
     *
     * @param class-string<Iterator> $class
     * @return void
     */
    public function setIteratorClass(string $class): void
    {
        if (class_exists(class: $class)) {
            $this->iteratorClass = $class;

            return;
        }

        if (str_starts_with(haystack: $class, needle: '\\')) {
            $class = '\\' . $class;
            if (class_exists(class: $class)) {
                $this->iteratorClass = $class;

                return;
            }
        }

        throw new Exception\InvalidArgumentException(message: 'The iterator class does not exist');
    }

    /**
     * Sort the entries with a user-defined comparison function and maintain key association
     *
     * @param callable(TValue, TValue): int $function
     * @return void
     */
    public function uasort(callable $function): void
    {
        if (is_callable(value: $function)) {
            uasort(array: $this->storage, callback: $function);
        }
    }

    /**
     * Sort the entries by keys using a user-defined comparison function
     *
     * @param callable(TKey, TKey): int $function
     * @return void
     */
    public function uksort(callable $function): void
    {
        if (is_callable(value: $function)) {
            uksort(array: $this->storage, callback: $function);
        }
    }

    /**
     * Unserialize an ArrayObject
     *
     * @param string $data
     * @return void
     */
    public function unserialize(string $data): void
    {
        $toUnserialize = unserialize(data: $data);
        if (! is_array(value: $toUnserialize)) {
            throw new UnexpectedValueException(message: sprintf(
                'Cannot deserialize %s instance; corrupt serialization data',
                self::class
            ));
        }

        $this->__unserialize(data: $toUnserialize);
    }

    /**
     * Magic method used to rebuild an instance.
     *
     * @param array $data Data array.
     * @return void
     */
    public function __unserialize(array $data)
    {
        $this->protectedProperties = array_keys(array: get_object_vars(object: $this));

        // Unserialize protected internal properties first
        if (array_key_exists(key: 'flag', array: $data)) {
            $this->setFlags(flags: (int)$data['flag']);
            unset($data['flag']);
        }

        if (array_key_exists(key: 'storage', array: $data)) {
            if (! is_array(value: $data['storage']) && ! is_object(value: $data['storage'])) {
                throw new UnexpectedValueException(message: sprintf(
                    'Cannot deserialize %s instance: corrupt storage data; expected array or object, received %s',
                    self::class,
                    gettype(value: $data['storage'])
                ));
            }
            $this->exchangeArray(data: $data['storage']);
            unset($data['storage']);
        }

        if (array_key_exists(key: 'iteratorClass', array: $data)) {
            if (! is_string(value: $data['iteratorClass'])) {
                throw new UnexpectedValueException(message: sprintf(
                    'Cannot deserialize %s instance: invalid iteratorClass; expected string, received %s',
                    self::class,
                    get_debug_type(value: $data['iteratorClass'])
                ));
            }
            $this->setIteratorClass(class: $data['iteratorClass']);
            unset($data['iteratorClass']);
        }

        unset($data['protectedProperties']);

        // Unserialize array keys after resolving protected properties to ensure configuration is used.
        foreach ($data as $k => $v) {
            $this->__set(key: $k, value: $v);
        }
    }
}
