<?php

if (!function_exists('md5')) {
    /** A raw MD5 hash has 16 bytes; its hexadecimal form has 32 characters.
     * @return non-empty-string
     */
    function md5(string $string, bool $binary = false): string {}
}
