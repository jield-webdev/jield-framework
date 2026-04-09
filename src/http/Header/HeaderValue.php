<?php

namespace Jield\Http\Header;

use function ord;
use function strlen;

final class HeaderValue
{
    /**
     * Private constructor; non-instantiable.
     */
    private function __construct()
    {
    }

    /**
     * Filter a header value
     *
     * Ensures CRLF header injection vectors are filtered.
     *
     * Per RFC 7230, only VISIBLE ASCII characters, spaces, and horizontal
     * tabs are allowed in values; only one whitespace character is allowed
     * between visible characters.
     *
     * @see http://en.wikipedia.org/wiki/HTTP_response_splitting
     *
     * @param string $value
     * @return string
     */
    public static function filter(string $value): string
    {
        $value  = $value;
        $length = strlen(string: $value);
        $string = '';
        for ($i = 0; $i < $length; $i += 1) {
            $ascii = ord(character: $value[$i]);

            // Non-visible, non-whitespace characters
            // 9 === horizontal tab
            // 32-126, 128-254 === visible
            // 127 === DEL
            // 255 === null byte
            if (
                ($ascii < 32 && $ascii !== 9)
                || $ascii === 127
                || $ascii > 254
            ) {
                continue;
            }

            $string .= $value[$i];
        }

        return $string;
    }

    /**
     * Validate a header value.
     *
     * Per RFC 7230, only VISIBLE ASCII characters, spaces, and horizontal
     * tabs are allowed in values; only one whitespace character is allowed
     * between visible characters.
     *
     * @see http://en.wikipedia.org/wiki/HTTP_response_splitting
     *
     * @param string $value
     * @return bool
     */
    public static function isValid(string $value): bool
    {
        $value  = $value;
        $length = strlen(string: $value);
        for ($i = 0; $i < $length; $i += 1) {
            $ascii = ord(character: $value[$i]);

            // Non-visible, non-whitespace characters
            // 9 === horizontal tab
            // 32-126, 128-254 === visible
            // 127 === DEL
            // 255 === null byte
            if (
                ($ascii < 32 && $ascii !== 9)
                || $ascii === 127
                || $ascii > 254
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Assert a header value is valid.
     *
     * @param string $value
     * @return void
     *@throws Exception\RuntimeException For invalid values.
     */
    public static function assertValid(string $value): void
    {
        if (! self::isValid(value: $value)) {
            throw new Exception\InvalidArgumentException(message: 'Invalid header value');
        }
    }
}
