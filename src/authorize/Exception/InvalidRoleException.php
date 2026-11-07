<?php

declare(strict_types=1);

namespace Jield\Authorize\Exception;

use function sprintf;

/**
 * Invalid role exception for BjyAuthorize
 */
class InvalidRoleException extends InvalidArgumentException
{
    public static function invalidRoleInstance(mixed $role): InvalidRoleException
    {
        return new self(message: sprintf('Invalid role of type "%s" provided', get_debug_type(value: $role)));
    }
}
