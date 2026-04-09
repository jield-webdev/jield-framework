<?php

namespace Jield\Mvc\Exception;

/**
 * @deprecated Since 3.2.0
 */
class ReachedFinalHandlerException extends RuntimeException
{
    /**
     * @return self
     */
    public static function create(): ReachedFinalHandlerException
    {
        return new self(message: 'Reached the final handler for middleware pipe - check the pipe configuration');
    }
}
