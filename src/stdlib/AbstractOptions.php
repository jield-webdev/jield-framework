<?php

declare(strict_types=1);

namespace Jield\Stdlib;

use Traversable;

use function array_shift;
use function assert;
use function get_object_vars;
use function is_array;
use function is_callable;
use function is_string;
use function method_exists;
use function preg_replace_callback;
use function sprintf;
use function str_replace;
use function strtolower;
use function ucwords;

/**
 * @template TValue
 * @implements ParameterObjectInterface<string, TValue>
 * @psalm-no-seal-properties This class has __get() magic. It exposes protected props when there is a matching method
 */
abstract class AbstractOptions implements ParameterObjectInterface
{
    // phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore,WebimpressCodingStandard.NamingConventions.ValidVariableName.NotCamelCapsProperty

    /**
     * We use the __ prefix to avoid collisions with properties in
     * user-implementations.
     *
     * @var bool
     */
    protected bool $__strictMode__ = true;

    // phpcs:enable

    /**
     * Constructor
     *
     * @param AbstractOptions<TValue>|iterable<string, TValue>|null $options
     */
    public function __construct(AbstractOptions|array|null $options = null)
    {
        if (null !== $options) {
            $this->setFromArray(options: $options);
        }
    }

    /**
     * Set one or more configuration properties
     *
     * @param AbstractOptions<TValue>|iterable<string, TValue> $options
     * @return AbstractOptions Provides fluent interface
     *@throws Exception\InvalidArgumentException
     */
    public function setFromArray(AbstractOptions|array $options): static
    {
        if ($options instanceof self) {
            $options = $options->toArray();
        }

        if (! is_array(value: $options) && ! $options instanceof Traversable) {
            throw new Exception\InvalidArgumentException(
                message: sprintf(
                    'Parameter provided to %s must be an %s, %s or %s',
                    __METHOD__,
                    'array',
                    'Traversable',
                    self::class
                )
            );
        }

        foreach ($options as $key => $value) {
            $this->__set(key: $key, value: $value);
        }

        return $this;
    }

    /**
     * Cast to array
     *
     * @return array<string, TValue>
     */
    public function toArray(): array
    {
        $array = [];

        $transform = static function (array $letters): string {
            /** @var list<string> $letters */
            $letter = array_shift(array: $letters);
            return '_' . strtolower(string: (string) $letter);
        };

        /** @psalm-var TValue $value */
        foreach (get_object_vars(object: $this) as $key => $value) {
            if ($key === '__strictMode__') {
                continue;
            }
            $normalizedKey = preg_replace_callback(pattern: '/([A-Z])/', callback: $transform, subject: $key);
            assert(assertion: is_string(value: $normalizedKey));
            $array[$normalizedKey] = $value;
        }

        return $array;
    }

    /**
     * Set a configuration property
     *
     * @param string $key
     * @param TValue|null $value
     * @return void
     *@throws Exception\BadMethodCallException
     * @see ParameterObject::__set()
     *
     */
    public function __set(string $key, $value)
    {
        $setter = 'set' . str_replace(search: '_', replace: '', subject: $key);

        if (is_callable(value: [$this, $setter])) {
            $this->{$setter}($value);

            return;
        }

        if ($this->__strictMode__) {
            throw new Exception\BadMethodCallException(message: sprintf(
                'The option "%s" does not have a callable "%s" ("%s") setter method which must be defined',
                $key,
                'set' . str_replace(search: ' ', replace: '', subject: ucwords(string: str_replace(search: '_', replace: ' ', subject: $key))),
                $setter
            ));
        }
    }

    /**
     * Get a configuration property
     *
     * @param string $key
     * @return TValue
     *@throws Exception\BadMethodCallException
     * @see ParameterObject::__get()
     *
     */
    public function __get(string $key)
    {
        $getter = 'get' . str_replace(search: '_', replace: '', subject: $key);

        if (is_callable(value: [$this, $getter])) {
            return $this->{$getter}();
        }

        throw new Exception\BadMethodCallException(message: sprintf(
            'The option "%s" does not have a callable "%s" getter method which must be defined',
            $key,
            'get' . str_replace(search: ' ', replace: '', subject: ucwords(string: str_replace(search: '_', replace: ' ', subject: $key)))
        ));
    }

    /**
     * Test if a configuration property is null
     *
     * @param string $key
     * @return bool
     *@see ParameterObject::__isset()
     *
     */
    public function __isset(string $key)
    {
        $getter = 'get' . str_replace(search: '_', replace: '', subject: $key);

        return method_exists(object_or_class: $this, method: $getter) && null !== $this->__get(key: $key);
    }

    /**
     * Set a configuration property to NULL
     *
     * @param string $key
     * @return void
     *@throws Exception\InvalidArgumentException
     * @see ParameterObject::__unset()
     *
     */
    public function __unset(string $key)
    {
        try {
            $this->__set(key: $key, value: null);
        } catch (Exception\BadMethodCallException $e) {
            throw new Exception\InvalidArgumentException(
                message: 'The class property $' . $key . ' cannot be unset as'
                . ' NULL is an invalid value for it',
                code: 0,
                previous: $e
            );
        }
    }
}
