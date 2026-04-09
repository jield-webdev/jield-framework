<?php // phpcs:disable WebimpressCodingStandard.NamingConventions.AbstractClass.Prefix


declare(strict_types=1);

namespace Jield\Stdlib;

use Jield\Stdlib\StringWrapper\Iconv;
use Jield\Stdlib\StringWrapper\Intl;
use Jield\Stdlib\StringWrapper\MbString;
use Jield\Stdlib\StringWrapper\Native;
use Jield\Stdlib\StringWrapper\StringWrapperInterface;

use function array_search;
use function defined;
use function extension_loaded;
use function in_array;
use function is_string;
use function preg_match;
use function strtoupper;

/**
 * Utility class for handling strings of different character encodings
 * using available PHP extensions.
 *
 * Declared abstract, as we have no need for instantiation.
 */
abstract class StringUtils
{
    /**
     * Ordered list of registered string wrapper instances
     *
     * @var list<class-string<StringWrapperInterface>>|null
     */
    protected static ?array $wrapperRegistry;

    /**
     * A list of known single-byte character encodings (upper-case)
     *
     * @var string[]
     */
    protected static array $singleByteEncodings = [
        'ASCII',
        '7BIT',
        '8BIT',
        'ISO-8859-1',
        'ISO-8859-2',
        'ISO-8859-3',
        'ISO-8859-4',
        'ISO-8859-5',
        'ISO-8859-6',
        'ISO-8859-7',
        'ISO-8859-8',
        'ISO-8859-9',
        'ISO-8859-10',
        'ISO-8859-11',
        'ISO-8859-13',
        'ISO-8859-14',
        'ISO-8859-15',
        'ISO-8859-16',
        'CP-1251',
        'CP-1252',
        // TODO
    ];

    /**
     * Is PCRE compiled with Unicode support?
     *
     * @var bool
     **/
    protected static bool $hasPcreUnicodeSupport;

    /**
     * Get registered wrapper classes
     *
     * @return string[]
     * @psalm-return list<class-string<StringWrapperInterface>>
     */
    public static function getRegisteredWrappers(): ?array
    {
        if (static::$wrapperRegistry === null) {
            static::$wrapperRegistry = [];

            if (extension_loaded(extension: 'intl')) {
                static::$wrapperRegistry[] = Intl::class;
            }

            if (extension_loaded(extension: 'mbstring')) {
                static::$wrapperRegistry[] = MbString::class;
            }

            if (extension_loaded(extension: 'iconv')) {
                static::$wrapperRegistry[] = Iconv::class;
            }

            static::$wrapperRegistry[] = Native::class;
        }

        return static::$wrapperRegistry;
    }

    /**
     * Register a string wrapper class
     *
     * @param class-string<StringWrapperInterface> $wrapper
     * @return void
     */
    public static function registerWrapper(string $wrapper): void
    {
        $wrapper = $wrapper;
        // using getRegisteredWrappers() here to ensure that the list is initialized
        if (! in_array(needle: $wrapper, haystack: static::getRegisteredWrappers(), strict: true)) {
            static::$wrapperRegistry[] = $wrapper;
        }
    }

    /**
     * Unregister a string wrapper class
     *
     * @param class-string<StringWrapperInterface> $wrapper
     * @return void
     */
    public static function unregisterWrapper(string $wrapper): void
    {
        // using getRegisteredWrappers() here to ensure that the list is initialized
        $index = array_search(needle: $wrapper, haystack: static::getRegisteredWrappers(), strict: true);
        if ($index !== false) {
            unset(static::$wrapperRegistry[$index]);
        }
    }

    /**
     * Reset all registered wrappers so the default wrappers will be used
     *
     * @return void
     */
    public static function resetRegisteredWrappers(): void
    {
        static::$wrapperRegistry = null;
    }

    /**
     * Get the first string wrapper supporting the given character encoding
     * and supports to convert into the given convert encoding.
     *
     * @param string $encoding        Character encoding to support
     * @param string|null $convertEncoding OPTIONAL character encoding to convert in
     * @return StringWrapperInterface
     * @throws Exception\RuntimeException If no wrapper supports given character encodings.
     */
    public static function getWrapper(string $encoding = 'UTF-8', ?string $convertEncoding = null): StringWrapperInterface
    {
        foreach (static::getRegisteredWrappers() as $wrapperClass) {
            if ($wrapperClass::isSupported(encoding: $encoding, convertEncoding: $convertEncoding)) {
                $wrapper = new $wrapperClass($encoding, $convertEncoding);
                $wrapper->setEncoding(encoding: $encoding, convertEncoding: $convertEncoding);
                return $wrapper;
            }
        }

        throw new Exception\RuntimeException(
            message: 'No wrapper found supporting "' . $encoding . '"'
            . ($convertEncoding !== null ? ' and "' . $convertEncoding . '"' : '')
        );
    }

    /**
     * Get a list of all known single-byte character encodings
     *
     * @return string[]
     */
    public static function getSingleByteEncodings(): array
    {
        return static::$singleByteEncodings;
    }

    /**
     * Check if a given encoding is a known single-byte character encoding
     *
     * @param string $encoding
     * @return bool
     */
    public static function isSingleByteEncoding(string $encoding): bool
    {
        return in_array(needle: strtoupper(string: $encoding), haystack: static::$singleByteEncodings);
    }

    /**
     * Check if a given string is valid UTF-8 encoded
     *
     * @param string $str
     * @return bool
     */
    public static function isValidUtf8(string $str): bool
    {
        return is_string(value: $str) && ($str === '' || preg_match(pattern: '/^./su', subject: $str) === 1);
    }

    /**
     * Is PCRE compiled with Unicode support?
     *
     * @return bool
     */
    public static function hasPcreUnicodeSupport(): bool
    {
        if (static::$hasPcreUnicodeSupport === null) {
            ErrorHandler::start();
            static::$hasPcreUnicodeSupport = defined(constant_name: 'PREG_BAD_UTF8_OFFSET_ERROR') && preg_match(pattern: '/\pL/u', subject: 'a') === 1;
            ErrorHandler::stop();
        }
        return static::$hasPcreUnicodeSupport;
    }
}
