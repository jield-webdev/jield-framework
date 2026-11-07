<?php

namespace Webmozart\Assert;

class Assert
{
    /**
     * The returned value is the same numeric value validated by is_numeric().
     * @return numeric
     */
    public static function numeric(mixed $value, string|callable $message = ''): int|float|string {}
}
