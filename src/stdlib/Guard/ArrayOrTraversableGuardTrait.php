<?php

declare(strict_types=1);

namespace Jield\Stdlib\Guard;

use Exception;
use Jield\Stdlib\Exception\InvalidArgumentException;
use Traversable;

use function get_debug_type;
use function is_array;
use function sprintf;

/**
 * Provide a guard method for array or Traversable data
 */
trait ArrayOrTraversableGuardTrait
{
    /**
     * Verifies that the data is an array or Traversable
     *
     * @param mixed  $data           the data to verify
     * @param string $dataName       the data name
     * @param string $exceptionClass FQCN for the exception
     * @return void
     * @throws Exception
     */
    protected function guardForArrayOrTraversable(
        mixed  $data,
        string $dataName = 'Argument',
        string $exceptionClass = InvalidArgumentException::class
    ): void
    {
        if (! is_array(value: $data) && ! $data instanceof Traversable) {
            $message = sprintf(
                "%s must be an array or Traversable, [%s] given",
                $dataName,
                get_debug_type(value: $data)
            );
            throw new $exceptionClass($message);
        }
    }
}
