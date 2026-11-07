<?php

declare(strict_types=1);

namespace Jield\Stdlib;

use ArrayAccess;
use ArrayIterator;
use ArrayObject;
use Countable;
use ReflectionClass;
use ReflectionMethod;
use Stringable;
use TypeError;

/** Validate values as they cross a dynamically typed integration boundary. */
// Preserve the existing public API and the state and operations that implement it.
// @mago-expect lint:cyclomatic-complexity(1)
// @mago-expect lint:kan-defect(1)
// @mago-expect lint:too-many-methods(1)
final class Value
{
    /** @return class-string */
    public static function classString(mixed $value): string
    {
        $value = self::string($value);
        if (!class_exists($value) && !interface_exists($value) && !enum_exists($value)) {
            throw self::invalid('existing class or interface name', $value);
        }
        return $value;
    }

    /**
     * @template T of object
     * @param class-string<T> $type
     * @return class-string<T>
     */
    public static function subclass(mixed $value, string $type): string
    {
        $value = self::classString($value);
        if (!is_a($value, $type, true)) {
            throw self::invalid($type . ' class name', $value);
        }
        return $value;
    }

    /** @return positive-int */
    public static function positiveInt(mixed $value): int
    {
        return is_int($value) && $value > 0 ? $value : throw self::invalid('positive integer', $value);
    }

    /** @return resource */
    public static function resource(mixed $value): mixed
    {
        return is_resource($value) ? $value : throw self::invalid('resource', $value);
    }

    /** @return array<non-empty-string, mixed> */
    public static function namedValues(mixed $value): array
    {
        $result = [];
        /** @mago-var mixed $item */
        foreach (self::stringKeyedArray($value) as $key => $item) {
            $result[self::nonEmptyString($key)] = $item;
        }
        return $result;
    }

    public static function nullableKey(mixed $value): int|string|null
    {
        return $value === null ? null : self::key($value);
    }

    public static function stringOrFalse(mixed $value): string|false
    {
        return $value === false ? false : self::string($value);
    }

    public static function keyOrFalse(mixed $value): int|string|false
    {
        return $value === false ? false : self::key($value);
    }

    /** @return array<array-key, array<string, mixed>> */
    public static function maps(mixed $value): array
    {
        $result = [];
        /** @mago-var mixed $item */
        foreach (self::array($value) as $key => $item) {
            $result[$key] = self::stringKeyedArray($item);
        }
        return $result;
    }

    /** @return list<string> */
    public static function stringList(mixed $value): array
    {
        return array_values(self::strings($value));
    }

    /** @return array<array-key, array-key> */
    public static function keys(mixed $value): array
    {
        $result = [];
        /** @mago-var mixed $item */
        foreach (self::array($value) as $key => $item) {
            $result[$key] = self::key($item);
        }
        return $result;
    }

    /** @return non-empty-string|null */
    public static function nullableNonEmptyString(mixed $value): ?string
    {
        return $value === null ? null : self::nonEmptyString($value);
    }

    /** @return int|numeric-string */
    public static function numericPort(mixed $value): int|string
    {
        if (is_int($value) || is_string($value) && is_numeric($value)) {
            return $value;
        }
        throw self::invalid('integer or numeric string', $value);
    }

    /**
     * @template T of object
     * @param class-string<T> $type
     * @return array<array-key, T>
     */
    public static function instances(mixed $value, string $type): array
    {
        $result = [];
        /** @mago-var mixed $item */
        foreach (self::array($value) as $key => $item) {
            $result[$key] = self::instance($item, $type);
        }
        return $result;
    }

    /**
     * @throws \ReflectionException
     */
    public static function property(mixed $value, string $name): mixed
    {
        $value = self::object($value);
        $properties = get_object_vars($value);
        if (array_key_exists($name, $properties)) {
            return $properties[$name];
        }
        if (is_callable([$value, '__get'])) {
            return self::call($value, '__get', [$name]);
        }
        throw new TypeError(sprintf('Property %s::$%s is not publicly accessible.', $value::class, $name));
    }

    /** Read nested integration metadata, checking each array boundary.
     * @param non-empty-list<array-key> $path
     */
    public static function path(mixed $target, array $path): mixed
    {
        foreach ($path as $key) {
            /** @var mixed $target */
            $target = self::accessible($target)[$key];
        }
        return $target;
    }

    public static function arrayOrIntOrString(mixed $value): array|int|string
    {
        return is_int($value) ? $value : self::arrayOrString($value);
    }

    /**
     * @template T of object
     * @param class-string<T> $type
     * @return T|string|null
     */
    public static function nullableInstanceOrString(mixed $value, string $type): object|string|null
    {
        return $value === null ? null : self::instanceOrString($value, $type);
    }

    /**
     * @template T of object
     * @param class-string<T> $type
     * @return T|array<array-key, mixed>|string|null
     */
    public static function instanceOrArrayOrString(mixed $value, string $type): object|array|string|null
    {
        return is_array($value) || $value === null ? $value : self::instanceOrString($value, $type);
    }

    /** Preserve PHP's array-cast semantics while retaining an array key type.
     * @return array<array-key, mixed>
     */
    // Keep the coordinated parsing or rendering steps of this compatibility method together.
    // @mago-expect lint:halstead(1)
    public static function toArray(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        if (is_array($value)) {
            return $value;
        }
        if ($value instanceof ArrayObject || $value instanceof ArrayIterator) {
            return $value->getArrayCopy();
        }
        return is_object($value) ? get_mangled_object_vars($value) : [$value];
    }

    /** @return array<string, int|float|string|null> */
    public static function routeParams(mixed $value): array
    {
        $values = self::stringKeyedArray($value);
        /** @mago-var mixed $item */
        foreach ($values as $item) {
            if (!is_int($item) && !is_float($item) && !is_string($item) && $item !== null) {
                throw self::invalid('route parameter', $item);
            }
        }
        /** @var array<string, int|float|string|null> $values */
        return $values;
    }

    /** @psalm-assert string $value */
    public static function string(mixed $value): string
    {
        return is_string($value) ? $value : throw self::invalid('string', $value);
    }

    /** @psalm-assert string|null $value */
    public static function nullableString(mixed $value): ?string
    {
        return $value === null ? null : self::string($value);
    }

    /** @psalm-assert int $value */
    public static function int(mixed $value): int
    {
        return is_int($value) ? $value : throw self::invalid('int', $value);
    }

    /** @psalm-assert int|float $value */
    public static function float(mixed $value): float
    {
        return is_float($value) || is_int($value) ? $value : throw self::invalid('float', $value);
    }

    /** @psalm-assert bool $value */
    public static function bool(mixed $value): bool
    {
        return is_bool($value) ? $value : throw self::invalid('bool', $value);
    }

    /** @return numeric */
    public static function numeric(mixed $value): mixed
    {
        return is_numeric($value) ? $value : throw self::invalid('numeric value', $value);
    }

    /** @return array<array-key, mixed>
     * @psalm-assert array<array-key, mixed> $value
     */
    public static function array(mixed $value): array
    {
        return is_array($value) ? $value : throw self::invalid('array', $value);
    }

    /** @psalm-assert object $value */
    public static function object(mixed $value): object
    {
        return is_object($value) ? $value : throw self::invalid('object', $value);
    }

    public static function nullableObject(mixed $value): ?object
    {
        return $value === null ? null : self::object($value);
    }

    /** @psalm-assert int|string $value */
    public static function key(mixed $value): int|string
    {
        return is_int($value) || is_string($value) ? $value : throw self::invalid('array key', $value);
    }

    /** @psalm-assert \Stringable|scalar|null $value */
    public static function scalar(mixed $value): Stringable|bool|int|float|string|null
    {
        return is_scalar($value) || $value === null || $value instanceof Stringable
            ? $value
            : throw self::invalid('scalar or stringable value', $value);
    }

    /** @psalm-assert callable $value */
    public static function callback(mixed $value): callable
    {
        return is_callable($value) ? $value : throw self::invalid('callable', $value);
    }

    /** @return array<array-key, mixed>|Countable
     * @psalm-assert array<array-key,mixed>|\Countable $value
     */
    public static function countable(mixed $value): array|Countable
    {
        return is_countable($value) ? $value : throw self::invalid('countable value', $value);
    }

    /**
     * @template T of object
     * @param class-string<T> $type
     * @return T
     * @psalm-assert T $value
     */
    public static function instance(mixed $value, string $type): object
    {
        return $value instanceof $type ? $value : throw self::invalid($type, $value);
    }

    /** @return array<string, mixed>
     * @psalm-assert array<string,mixed> $value
     */
    public static function stringKeyedArray(mixed $value): array
    {
        $array = self::array($value);
        foreach (array_keys($array) as $key) {
            if (!is_string($key)) {
                throw self::invalid('string array key', $key);
            }
        }

        /** @var array<string, mixed> $array */
        return $array;
    }

    /**
     * @template T of object
     * @param T|null $value
     * @return T
     * @psalm-assert !null $value
     */
    public static function present(?object $value): object
    {
        return $value ?? throw self::invalid('non-null object', $value);
    }

    /** @return non-empty-string */
    public static function nonEmptyString(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : throw self::invalid('non-empty string', $value);
    }

    /** @return non-negative-int */
    public static function nonNegativeInt(mixed $value): int
    {
        return is_int($value) && $value >= 0 ? $value : throw self::invalid('non-negative integer', $value);
    }

    public static function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : self::int($value);
    }

    public static function objectOrString(mixed $value): object|string
    {
        return is_object($value) || is_string($value) ? $value : throw self::invalid('object or string', $value);
    }

    public static function plainScalar(mixed $value): bool|float|int|string|null
    {
        return is_scalar($value) || $value === null ? $value : throw self::invalid('scalar value', $value);
    }

    public static function arrayOrString(mixed $value): array|string
    {
        return is_array($value) || is_string($value) ? $value : throw self::invalid('array or string', $value);
    }

    public static function nullableArray(mixed $value): ?array
    {
        return $value === null ? null : self::array($value);
    }

    public static function nullableArrayOrObject(mixed $value): array|object|null
    {
        return $value === null ? null : self::arrayOrObject($value);
    }

    public static function nullableArrayOrString(mixed $value): array|string|null
    {
        return $value === null ? null : self::arrayOrString($value);
    }

    public static function arrayOrObject(mixed $value): array|object
    {
        return is_array($value) || is_object($value) ? $value : throw self::invalid('array or object', $value);
    }

    /** @return iterable<mixed, mixed> */
    public static function iterable(mixed $value): iterable
    {
        return is_iterable($value) ? $value : throw self::invalid('iterable value', $value);
    }

    /**
     * @template T of object
     * @param class-string<T> $type
     * @return T|string
     */
    public static function instanceOrString(mixed $value, string $type): object|string
    {
        return is_string($value) ? $value : self::instance($value, $type);
    }

    /** @return array<array-key, Stringable|scalar|null> */
    public static function scalars(mixed $value): array
    {
        $result = [];
        /** @mago-var mixed $item */
        foreach (self::array($value) as $key => $item) {
            $result[$key] = self::scalar($item);
        }
        return $result;
    }

    /** @return array<string, scalar|null> */
    public static function scalarMap(mixed $value): array
    {
        $result = [];
        /** @mago-var mixed $item */
        foreach (self::stringKeyedArray($value) as $key => $item) {
            $result[$key] = self::plainScalar($item);
        }
        return $result;
    }

    /** @return array<array-key, string> */
    public static function strings(mixed $value): array
    {
        $result = [];
        /** @mago-var mixed $item */
        foreach (self::array($value) as $key => $item) {
            $result[$key] = self::string($item);
        }
        return $result;
    }

    /** @return array<array-key, string>|string */
    public static function stringsOrString(mixed $value): array|string
    {
        return is_array($value) ? self::strings($value) : self::string($value);
    }

    /**
     * @template TValue
     * @param array<array-key, TValue> $allowed
     * @return TValue
     */
    public static function oneOf(mixed $value, array $allowed): mixed
    {
        foreach ($allowed as $candidate) {
            if ($candidate === $value) {
                return $candidate;
            }
        }

        throw self::invalid('one of the allowed values', $value);
    }

    /** @return iterable<non-empty-string, mixed> */
    public static function namedIterable(mixed $value): iterable
    {
        /**
         * @mago-var mixed $key
         * @mago-var mixed $item
         */
        foreach (self::iterable($value) as $key => $item) {
            yield self::nonEmptyString($key) => $item;
        }
    }

    /** @return \Laminas\Stdlib\AbstractOptions<mixed>|array<string, mixed>|null */
    // Keep the coordinated parsing or rendering steps of this compatibility method together.
    // @mago-expect lint:halstead(1)
    public static function options(mixed $value): \Laminas\Stdlib\AbstractOptions|array|null
    {
        if ($value === null || $value instanceof \Laminas\Stdlib\AbstractOptions) {
            return $value;
        }

        if ($value instanceof \Traversable) {
            $value = iterator_to_array($value);
        }

        return self::stringKeyedArray($value);
    }

    /** @return array<string, string> */
    public static function stringMap(mixed $value): array
    {
        $result = [];
        /** @mago-var mixed $item */
        foreach (self::stringKeyedArray($value) as $key => $item) {
            $result[$key] = self::string($item);
        }
        return $result;
    }

    /** @return array<array-key, mixed>|ArrayAccess<mixed, mixed> */
    public static function accessible(mixed $value): array|ArrayAccess
    {
        return is_array($value) || $value instanceof ArrayAccess
            ? $value
            : throw self::invalid('array or ArrayAccess', $value);
    }

    public static function truthy(mixed $value): bool
    {
        return !!$value;
    }

    public static function stringify(mixed $value): string
    {
        return (string) self::scalar($value);
    }

    /**
     * @template T of object
     * @param class-string<T> $type
     * @return T|null
     */
    public static function nullableInstance(mixed $value, string $type): ?object
    {
        return $value === null ? null : self::instance($value, $type);
    }

    /**
     * @param array<array-key, mixed> $arguments
     * @param class-string|null $scope Scope of an internal call to a protected/private method.
     * @throws \ReflectionException
     */
    public static function call(mixed $object, string $method, array $arguments = [], ?string $scope = null): mixed
    {
        if (!is_object($object)) {
            throw self::invalid('object', $object);
        }
        if ($scope !== null && method_exists($object, $method)) {
            $reflection = new ReflectionMethod($object, $method);
            $declaringClass = $reflection->getDeclaringClass()->getName();
            $inScope =
                $scope === $declaringClass
                || !$reflection->isPrivate()
                && (
                    new ReflectionClass($scope)->isSubclassOf($declaringClass)
                    || $reflection->getDeclaringClass()->isSubclassOf($scope)
                );
            if (!$reflection->isPublic() && $inScope) {
                return $reflection->invokeArgs($object, $arguments);
            }
        }
        $callback = [$object, $method];
        if (!is_callable($callback)) {
            throw new TypeError(sprintf('Method %s::%s is not callable.', $object::class, $method));
        }

        return $callback(...$arguments);
    }

    /** Construct a configured class after resolving its runtime identity.
     * @param array<array-key, mixed> $arguments
     * @throws \ReflectionException
     */
    public static function instantiate(mixed $class, array $arguments = []): object
    {
        $class = is_object($class) ? $class::class : self::string($class);
        if (!class_exists($class)) {
            throw new TypeError(sprintf('Configured class "%s" does not exist.', $class));
        }
        $reflection = new ReflectionClass(self::classString($class));

        return self::object(
            $reflection->getConstructor() === null
                ? $reflection->newInstance()
                : $reflection->newInstanceArgs($arguments),
        );
    }

    /**
     * @template T
     * @param T $target
     * @param non-empty-list<array-key|null> $path Null appends a new element.
     * @psalm-param-out (T is \ArrayAccess ? \ArrayAccess : array<array-key, mixed>) $target
     */
    public static function setPath(mixed &$target, array $path, mixed $value): void
    {
        if ($target === null || $target === false) {
            $target = [];
        }
        if (!is_array($target) && !$target instanceof ArrayAccess) {
            throw self::invalid('array or ArrayAccess', $target);
        }

        $key = array_shift($path);
        if ($path !== []) {
            /** @var mixed $child */
            $child = $key === null ? null : $target[$key] ?? null;
            self::setPath($child, $path, $value);
            $value = $child;
        }
        if ($key === null) {
            $target[] = $value;
            return;
        }
        $target[$key] = $value;
    }

    private static function invalid(string $expected, mixed $value): TypeError
    {
        return new TypeError(sprintf('Expected %s; received %s.', $expected, get_debug_type($value)));
    }
}
