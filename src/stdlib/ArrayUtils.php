<?php // phpcs:disable WebimpressCodingStandard.NamingConventions.AbstractClass.Prefix


declare(strict_types=1);

namespace Jield\Stdlib;

use Iterator;
use Jield\Stdlib\ArrayUtils\MergeRemoveKey;
use Jield\Stdlib\ArrayUtils\MergeReplaceKeyInterface;
use Traversable;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_values;
use function in_array;
use function is_array;
use function is_float;
use function is_int;
use function is_object;
use function is_scalar;
use function is_string;
use function iterator_to_array;
use function method_exists;

/**
 * Utility class for testing and manipulation of PHP arrays.
 *
 * Declared abstract, as we have no need for instantiation.
 */
abstract class ArrayUtils
{
    /**
     * Test whether an array contains one or more string keys
     *
     * @param bool $allowEmpty Should an empty array() return true
     * @return bool
     */
    public static function hasStringKeys(mixed $value, bool $allowEmpty = false): bool
    {
        if (!is_array(value: $value)) {
            return false;
        }

        if (!$value) {
            return $allowEmpty;
        }

        return [] !== array_filter(array: array_keys(array: $value), callback: 'is_string');
    }

    /**
     * Test whether an array contains one or more integer keys
     *
     * @param bool $allowEmpty Should an empty array() return true
     * @return bool
     */
    public static function hasIntegerKeys(mixed $value, bool $allowEmpty = false): bool
    {
        if (!is_array(value: $value)) {
            return false;
        }

        if (!$value) {
            return $allowEmpty;
        }

        return [] !== array_filter(array: array_keys(array: $value), callback: 'is_int');
    }

    /**
     * Test whether an array contains one or more numeric keys.
     *
     * A numeric key can be one of the following:
     * - an integer 1,
     * - a string with a number '20'
     * - a string with negative number: '-1000'
     * - a float: 2.2120, -78.150999
     * - a string with float:  '4000.99999', '-10.10'
     *
     * @param bool $allowEmpty Should an empty array() return true
     * @return bool
     */
    public static function hasNumericKeys(mixed $value, bool $allowEmpty = false): bool
    {
        if (!is_array(value: $value)) {
            return false;
        }

        if (!$value) {
            return $allowEmpty;
        }

        return [] !== array_filter(array: array_keys(array: $value), callback: 'is_numeric');
    }

    /**
     * Test whether an array is a list
     *
     * A list is a collection of values assigned to continuous integer keys
     * starting at 0 and ending at count() - 1.
     *
     * For example:
     * <code>
     * $list = array('a', 'b', 'c', 'd');
     * $list = array(
     *     0 => 'foo',
     *     1 => 'bar',
     *     2 => array('foo' => 'baz'),
     * );
     * </code>
     *
     * @param bool $allowEmpty Is an empty list a valid list?
     * @return bool
     */
    public static function isList(mixed $value, bool $allowEmpty = false): bool
    {
        if (!is_array(value: $value)) {
            return false;
        }

        if (!$value) {
            return $allowEmpty;
        }

        return array_values(array: $value) === $value;
    }

    /**
     * Test whether an array is a hash table.
     *
     * An array is a hash table if:
     *
     * 1. Contains one or more non-integer keys, or
     * 2. Integer keys are non-continuous or misaligned (not starting with 0)
     *
     * For example:
     * <code>
     * $hash = array(
     *     'foo' => 15,
     *     'bar' => false,
     * );
     * $hash = array(
     *     1995  => 'Birth of PHP',
     *     2009  => 'PHP 5.3.0',
     *     2012  => 'PHP 5.4.0',
     * );
     * $hash = array(
     *     'formElement,
     *     'options' => array( 'debug' => true ),
     * );
     * </code>
     *
     * @param bool $allowEmpty Is an empty array() a valid hash table?
     * @return bool
     */
    public static function isHashTable(mixed $value, bool $allowEmpty = false): bool
    {
        if (!is_array(value: $value)) {
            return false;
        }

        if (!$value) {
            return $allowEmpty;
        }

        return array_values(array: $value) !== $value;
    }

    /**
     * Checks if a value exists in an array.
     *
     * Due to "foo" == 0 === TRUE with in_array when strict = false, an option
     * has been added to prevent this. When $strict = 0/false, the most secure
     * non-strict check is implemented. if $strict = -1, the default in_array
     * non-strict behaviour is used.
     *
     * @param bool|int $strict
     * @return bool
     * @deprecated This method will be removed in version 4.0 of this component
     *
     */
    public static function inArray(mixed $needle, array $haystack, bool|int $strict = false): bool
    {
        if ((bool)$strict === false) {
            if (is_int(value: $needle) || is_float(value: $needle)) {
                $needle = (string)$needle;
            }
            if (is_string(value: $needle)) {
                foreach ($haystack as &$h) {
                    if (is_int(value: $h) || is_float(value: $h)) {
                        $h = (string)$h;
                    }
                }
            }
        }

        return in_array(needle: $needle, haystack: $haystack, strict: (bool)$strict);
    }

    /**
     * Converts an iterator to an array. The $recursive flag, on by default,
     * hints whether or not you want to do so recursively.
     *
     * @template TKey
     * @template TValue
     * @param iterable<TKey, TValue> $iterator The array or Traversable object to convert
     * @param bool $recursive Recursively check all nested structures
     * @return array<TKey, TValue>
     * @throws Exception\InvalidArgumentException If $iterator is not an array or a Traversable object.
     */
    public static function iteratorToArray(iterator $iterator, bool $recursive = true): array
    {
        /** @psalm-suppress DocblockTypeContradiction */
        if (!is_array(value: $iterator) && !$iterator instanceof Traversable) {
            throw new Exception\InvalidArgumentException(message: __METHOD__ . ' expects an array or Traversable object');
        }

        if (!$recursive) {
            if (is_array(value: $iterator)) {
                return $iterator;
            }

            return iterator_to_array(iterator: $iterator);
        }

        if (
            is_object(value: $iterator)
            && !$iterator instanceof Iterator
            && method_exists(object_or_class: $iterator, method: 'toArray')
        ) {
            /** @psalm-var array<TKey, TValue> $array */
            $array = $iterator->toArray();

            return $array;
        }

        $array = [];
        foreach ($iterator as $key => $value) {
            if (null === $key) {
                continue;
            }
            if (is_scalar(value: $value)) {
                $array[$key] = $value;
                continue;
            }

            if ($value instanceof Traversable) {
                $array[$key] = static::iteratorToArray(iterator: $value, recursive: $recursive);
                continue;
            }

            if (is_array(value: $value)) {
                $array[$key] = static::iteratorToArray(iterator: $value, recursive: $recursive);
                continue;
            }

            $array[$key] = $value;
        }

        /** @psalm-var array<TKey, TValue> $array */

        return $array;
    }

    /**
     * Merge two arrays together.
     *
     * If an integer key exists in both arrays and preserveNumericKeys is false, the value
     * from the second array will be appended to the first array. If both values are arrays, they
     * are merged together, else the value of the second array overwrites the one of the first array.
     *
     * @param bool $preserveNumericKeys
     * @return array
     */
    public static function merge(array $a, array $b, bool $preserveNumericKeys = false): array
    {
        foreach ($b as $key => $value) {
            if ($value instanceof MergeReplaceKeyInterface) {
                $a[$key] = $value->getData();
            } elseif (isset($a[$key]) || array_key_exists(key: $key, array: $a)) {
                if ($value instanceof MergeRemoveKey) {
                    unset($a[$key]);
                } elseif (!$preserveNumericKeys && is_int(value: $key)) {
                    $a[] = $value;
                } elseif (is_array(value: $value) && is_array(value: $a[$key])) {
                    $a[$key] = static::merge(a: $a[$key], b: $value, preserveNumericKeys: $preserveNumericKeys);
                } else {
                    $a[$key] = $value;
                }
            } else if (!$value instanceof MergeRemoveKey) {
                $a[$key] = $value;
            }
        }

        return $a;
    }
}
