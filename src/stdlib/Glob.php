<?php // phpcs:disable WebimpressCodingStandard.NamingConventions.AbstractClass.Prefix,Generic.NamingConventions.ConstructorName.OldStyle


declare(strict_types=1);

namespace Jield\Stdlib;

use function array_merge;
use function array_unique;
use function defined;
use function glob;
use function strlen;
use function strpos;
use function substr;

use const GLOB_BRACE;
use const GLOB_ERR;
use const GLOB_MARK;
use const GLOB_NOCHECK;
use const GLOB_NOESCAPE;
use const GLOB_NOSORT;
use const GLOB_ONLYDIR;

/**
 * Wrapper for glob with fallback if GLOB_BRACE is not available.
 */
abstract class Glob
{
    /**#@+
     * Glob constants.
     */
    public const int GLOB_MARK     = 0x01;
    public const int GLOB_NOSORT   = 0x02;
    public const int GLOB_NOCHECK  = 0x04;
    public const int GLOB_NOESCAPE = 0x08;
    public const int GLOB_BRACE    = 0x10;
    public const int GLOB_ONLYDIR  = 0x20;
    public const int GLOB_ERR      = 0x40;
    /**#@-*/

    /**
     * Find pathnames matching a pattern.
     *
     * @see    http://docs.php.net/glob
     *
     * @param string $pattern
     * @param int $flags
     * @param bool $forceFallback
     * @return array
     * @throws Exception\RuntimeException
     */
    public static function glob(string $pattern, int $flags = 0, bool $forceFallback = false): array
    {
        if (! defined(constant_name: 'GLOB_BRACE') || $forceFallback) {
            return static::fallbackGlob(pattern: $pattern, flags: $flags);
        }

        return static::systemGlob(pattern: $pattern, flags: $flags);
    }

    /**
     * Use the glob function provided by the system.
     *
     * @param string $pattern
     * @param int $flags
     * @return array
     * @throws Exception\RuntimeException
     */
    protected static function systemGlob(string $pattern, int $flags): array
    {
        if ($flags) {
            $flagMap = [
                self::GLOB_MARK     => GLOB_MARK,
                self::GLOB_NOSORT   => GLOB_NOSORT,
                self::GLOB_NOCHECK  => GLOB_NOCHECK,
                self::GLOB_NOESCAPE => GLOB_NOESCAPE,
                self::GLOB_BRACE    => defined(constant_name: 'GLOB_BRACE') ? GLOB_BRACE : 0,
                self::GLOB_ONLYDIR  => GLOB_ONLYDIR,
                self::GLOB_ERR      => GLOB_ERR,
            ];

            $globFlags = 0;

            foreach ($flagMap as $internalFlag => $globFlag) {
                if ($flags & $internalFlag) {
                    $globFlags |= $globFlag;
                }
            }
        } else {
            $globFlags = 0;
        }

        ErrorHandler::start();
        $res = glob(pattern: $pattern, flags: $globFlags);
        $err = ErrorHandler::stop();
        if ($res === false) {
            throw new Exception\RuntimeException(message: "glob('{$pattern}', {$globFlags}) failed", code: 0, previous: $err);
        }
        return $res;
    }

    /**
     * Expand braces manually, then use the system glob.
     *
     * @param string $pattern
     * @param int $flags
     * @return array
     * @throws Exception\RuntimeException
     */
    protected static function fallbackGlob(string $pattern, int $flags): array
    {
        if (! self::flagsIsEqualTo(flags: $flags, otherFlags: self::GLOB_BRACE)) {
            return static::systemGlob(pattern: $pattern, flags: $flags);
        }

        $flags &= ~self::GLOB_BRACE;
        $length = strlen(string: $pattern);
        $paths  = [];

        if ($flags & self::GLOB_NOESCAPE) {
            $begin = strpos(haystack: $pattern, needle: '{');
        } else {
            $begin = 0;

            while (true) {
                if ($begin === $length) {
                    $begin = false;
                    break;
                } elseif ($pattern[$begin] === '\\' && ($begin + 1) < $length) {
                    $begin++;
                } elseif ($pattern[$begin] === '{') {
                    break;
                }

                $begin++;
            }
        }

        if ($begin === false) {
            return static::systemGlob(pattern: $pattern, flags: $flags);
        }

        $next = static::nextBraceSub(pattern: $pattern, begin: $begin + 1, flags: $flags);

        if ($next === null) {
            return static::systemGlob(pattern: $pattern, flags: $flags);
        }

        $rest = $next;

        while ($pattern[$rest] !== '}') {
            $rest = static::nextBraceSub(pattern: $pattern, begin: $rest + 1, flags: $flags);

            if ($rest === null) {
                return static::systemGlob(pattern: $pattern, flags: $flags);
            }
        }

        $p = $begin + 1;

        while (true) {
            $subPattern = substr(string: $pattern, offset: 0, length: $begin)
                        . substr(string: $pattern, offset: $p, length: $next - $p)
                        . substr(string: $pattern, offset: $rest + 1);

            $result = static::fallbackGlob(pattern: $subPattern, flags: $flags | self::GLOB_BRACE);

            if ($result) {
                $paths = array_merge($paths, $result);
            }

            if ($pattern[$next] === '}') {
                break;
            }

            $p    = $next + 1;
            $next = static::nextBraceSub(pattern: $pattern, begin: $p, flags: $flags);
        }

        return array_unique(array: $paths);
    }

    /**
     * Find the end of the sub-pattern in a brace expression.
     *
     * @param string $pattern
     * @param int $begin
     * @param int $flags
     * @return int|null
     */
    protected static function nextBraceSub(string $pattern, int $begin, int $flags): ?int
    {
        $length  = strlen(string: $pattern);
        $depth   = 0;
        $current = $begin;

        while ($current < $length) {
            $flagsEqualsNoEscape = self::flagsIsEqualTo(flags: $flags, otherFlags: self::GLOB_NOESCAPE);

            if ($flagsEqualsNoEscape && $pattern[$current] === '\\') {
                if (++$current === $length) {
                    break;
                }

                $current++;
            } else if (
                ($pattern[$current] === '}' && $depth-- === 0)
                || ($pattern[$current] === ',' && $depth === 0)
            ) {
                break;
            } elseif ($pattern[$current++] === '{') {
                $depth++;
            }
        }

        return $current < $length ? $current : null;
    }

    /** @internal */
    public static function flagsIsEqualTo(int $flags, int $otherFlags): bool
    {
        return (bool) ($flags & $otherFlags);
    }
}
