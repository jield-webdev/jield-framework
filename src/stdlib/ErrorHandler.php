<?php // phpcs:disable WebimpressCodingStandard.NamingConventions.AbstractClass.Prefix


declare(strict_types=1);

namespace Jield\Stdlib;

use ErrorException;

use function array_pop;
use function count;
use function restore_error_handler;
use function set_error_handler;

use const E_WARNING;

/**
 * ErrorHandler that can be used to catch internal PHP errors
 * and convert to an ErrorException instance.
 */
abstract class ErrorHandler
{
    /**
     * Active stack
     *
     * @var list<ErrorException|null>
     */
    protected static array $stack = [];

    /**
     * Check if this error handler is active
     *
     * @return bool
     */
    public static function started(): bool
    {
        return (bool) static::getNestedLevel();
    }

    /**
     * Get the current nested level
     *
     * @return int
     */
    public static function getNestedLevel(): int
    {
        return count(value: static::$stack);
    }

    /**
     * Starting the error handler
     *
     * @param int $errorLevel
     * @return void
     */
    public static function start(int $errorLevel = E_WARNING): void
    {
        if (! static::$stack) {
            set_error_handler(callback: [static::class, 'addError'], error_levels: $errorLevel);
        }

        static::$stack[] = null;
    }

    /**
     * Stopping the error handler
     *
     * @param bool $throw Throw the ErrorException if any
     * @return null|ErrorException
     * @throws ErrorException If an error has been caught and $throw is true.
     */
    public static function stop(bool $throw = false): ?ErrorException
    {
        $errorException = null;

        if (static::$stack) {
            $errorException = array_pop(array: static::$stack);

            if (! static::$stack) {
                restore_error_handler();
            }

            if ($errorException && $throw) {
                throw $errorException;
            }
        }

        return $errorException;
    }

    /**
     * Stop all active handler
     *
     * @return void
     */
    public static function clean(): void
    {
        if (static::$stack) {
            restore_error_handler();
        }

        static::$stack = [];
    }

    /**
     * Add an error to the stack
     *
     * @param int $errno
     * @param string $errstr
     * @param string $errfile
     * @param int $errline
     * @return void
     */
    public static function addError(int $errno, string $errstr = '', string $errfile = '', int $errline = 0): void
    {
        $stack = &static::$stack[count(value: static::$stack) - 1];
        $stack = new ErrorException(message: $errstr, code: 0, severity: $errno, filename: $errfile, line: $errline, previous: $stack);
    }
}
