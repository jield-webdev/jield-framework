<?php

declare(strict_types=1);

namespace Jield\Mvc\Exception;

/**
 * @deprecated Since 3.2.0
 */
class ReachedFinalHandlerException extends RuntimeException
{
    public static function create(): ReachedFinalHandlerException
    {
        return new self('Reached the final handler for middleware pipe - check the pipe configuration');
    }
}
