<?php

namespace Jield\Mvc\Exception;

use function sprintf;

/**
 * @deprecated Since 3.2.0
 */
class InvalidMiddlewareException extends RuntimeException
{
    private ?string $middlewareName = null;

    /**
     * @param string $middlewareName
     * @return self
     */
    public static function fromMiddlewareName(string $middlewareName): InvalidMiddlewareException
    {
        $middlewareName           = $middlewareName;
        $instance                 = new self(message: sprintf('Cannot dispatch middleware %s', $middlewareName));
        $instance->middlewareName = $middlewareName;
        return $instance;
    }

    /**
     * @return self
     */
    public static function fromNull(): InvalidMiddlewareException
    {
        return new self(message: 'Middleware name cannot be null');
    }

    /**
     * @return string
     */
    public function toMiddlewareName(): string
    {
        return $this->middlewareName ?? '';
    }
}
