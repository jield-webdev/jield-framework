<?php

declare(strict_types=1);

namespace Jield\Search\Solr;

use function defined;
use function gettype;
use function ini_get;
use function is_string;
use function number_format;
use function str_replace;

final class Util
{
    /** @var list<string> */
    private static array $search = [
        ' ',
        '\\',
        '+',
        '-',
        '&',
        '|',
        '!',
        '(',
        ')',
        '{',
        '}',
        '[',
        ']',
        '^',
        '"',
        '~',
        '*',
        '?',
        ':',
        '/',
    ];

    /** @var list<string> */
    private static array $replace = [
        '\+',
        '\\\\',
        '\+',
        '\-',
        '\&',
        '\|',
        '\!',
        '\(',
        '\)',
        '\{',
        '\}',
        '\[',
        '\]',
        '\^',
        '\"',
        '\~',
        '\*',
        '\?',
        '\:',
        '\/',
    ];

    /**
     * Quote a given string
     */
    public static function quote(mixed $value): string
    {
        if ($value instanceof ExpressionInterface) {
            return (string) $value;
        }

        if (!is_string(value: $value)) {
            if (is_scalar($value) || $value === null || $value instanceof \Stringable) {
                return (string) $value;
            }
            throw new \InvalidArgumentException('Expected a scalar or stringable Solr value.');
        }

        return '"' . str_replace(search: self::$search, replace: self::$replace, subject: $value) . '"';
    }

    /**
     * Sanitizes a string
     *
     * Puts quotes around a string, treats everything else as a term
     *
     * @param mixed $value
     * @return string
     */
    public static function sanitize(mixed $value): string
    {
        if (is_string($value)) {
            return $value === '' ? '' : '"' . str_replace(self::$search, self::$replace, $value) . '"';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            $precision = defined('HHVM_VERSION') ? 14 : (int) ini_get('precision');
            return number_format($value, $precision, '.', '');
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value instanceof \Stringable) {
            return (string) $value;
        }
        if ($value === null || $value === []) {
            return '';
        }
        throw new \InvalidArgumentException('Expected a scalar or stringable Solr value.');
    }

    /**
     * Escape a string to be safe for Solr queries
     */
    public static function escape(mixed $value): string
    {
        if ($value instanceof ExpressionInterface) {
            return (string) $value;
        }

        if (!is_string(value: $value)) {
            if (is_scalar($value) || $value === null || $value instanceof \Stringable) {
                return (string) $value;
            }
            throw new \InvalidArgumentException('Expected a scalar or stringable Solr value.');
        }

        return str_replace(search: self::$search, replace: self::$replace, subject: $value);
    }
}
