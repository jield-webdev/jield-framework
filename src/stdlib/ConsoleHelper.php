<?php

declare(strict_types=1);

namespace Jield\Stdlib;

use function function_exists;
use function fwrite;
use function getenv;
use function posix_isatty;
use function preg_replace;
use function sprintf;
use function str_replace;

use const DIRECTORY_SEPARATOR;
use const PHP_EOL;
use const STDERR;
use const STDOUT;

/**
 * Utilities for console tooling.
 *
 * Provides the following facilities:
 *
 * - Colorize strings using markup (e.g., `<info>message</info>`,
 *   `<error>message</error>`)
 * - Write output to a specified stream, optionally with colorization.
 * - Write a line of output to a specified stream, optionally with
 *   colorization, using the system EOL sequence..
 * - Write an error message to STDERR.
 *
 * Colorization will only occur when expected sequences are discovered, and
 * then, only if the console terminal allows it.
 *
 * Essentially, provides the bare minimum to allow you to provide messages to
 * the current console.
 */
class ConsoleHelper
{
    public const string COLOR_GREEN = "\033[32m";
    public const string COLOR_RED   = "\033[31m";
    public const string COLOR_RESET = "\033[0m";

    public const string HIGHLIGHT_INFO  = 'info';
    public const string HIGHLIGHT_ERROR = 'error';

    /** @psalm-var array<ConsoleHelper::HIGHLIGHT_*, ConsoleHelper::COLOR_GREEN|ConsoleHelper::COLOR_RED> */
    private array $highlightMap = [
        self::HIGHLIGHT_INFO  => self::COLOR_GREEN,
        self::HIGHLIGHT_ERROR => self::COLOR_RED,
    ];

    /** @var string Exists only for testing. */
    private string $eol = PHP_EOL;

    /** @var resource Exists only for testing. */
    private $stderr = STDERR;

    private bool $supportsColor;

    /**
     * @param false|resource $resource
     */
    public function __construct(false $resource = STDOUT)
    {
        $this->supportsColor = $this->detectColorCapabilities(resource: $resource);
    }

    /**
     * Colorize a string for use with the terminal.
     *
     * Takes strings formatted as `<key>string</key>` and formats them per the
     * $highlightMap; if color support is disabled, simply removes the formatting
     * tags.
     *
     * @param string $string
     * @return string
     */
    public function colorize(string $string): string
    {
        $reset = $this->supportsColor ? self::COLOR_RESET : '';
        foreach ($this->highlightMap as $key => $color) {
            $pattern = sprintf('#<%s>(.*?)</%s>#s', $key, $key);
            $color   = $this->supportsColor ? $color : '';
            $string  = (string) preg_replace(pattern: $pattern, replacement: $color . '$1' . $reset, subject: $string);
        }
        return $string;
    }

    /**
     * @param string $string
     * @param bool $colorize Whether or not to colorize the string
     * @param false|resource $resource Defaults to STDOUT
     * @return void
     */
    public function write(string $string, bool $colorize = true, false $resource = STDOUT): void
    {
        if ($colorize) {
            $string = $this->colorize(string: $string);
        }

        $string = $this->formatNewlines(string: $string);

        fwrite(stream: $resource, data: $string);
    }

    /**
     * @param string $string
     * @param bool $colorize Whether or not to colorize the line
     * @param false|resource $resource Defaults to STDOUT
     * @return void
     */
    public function writeLine(string $string, bool $colorize = true, false $resource = STDOUT): void
    {
        $this->write(string: $string . $this->eol, colorize: $colorize, resource: $resource);
    }

    /**
     * Emit an error message.
     *
     * Wraps the message in `<error></error>`, and passes it to `writeLine()`,
     * using STDERR as the resource; emits an additional empty line when done,
     * also to STDERR.
     *
     * @param string $message
     * @return void
     */
    public function writeErrorMessage(string $message): void
    {
        $this->writeLine(string: sprintf('<error>%s</error>', $message), colorize: true, resource: $this->stderr);
        $this->writeLine(string: '', colorize: false, resource: $this->stderr);
    }

    /**
     * @param false|resource $resource
     * @return bool
     */
    private function detectColorCapabilities(false $resource = STDOUT): bool
    {
        if ('\\' === DIRECTORY_SEPARATOR) {
            // Windows
            return false !== getenv(name: 'ANSICON')
                || 'ON' === getenv(name: 'ConEmuANSI')
                || 'xterm' === getenv(name: 'TERM');
        }

        return function_exists(function: 'posix_isatty') && posix_isatty(file_descriptor: $resource);
    }

    /**
     * Ensure newlines are appropriate for the current terminal.
     *
     * @param string $string
     * @return string
     */
    private function formatNewlines(string $string): string
    {
        $string = str_replace(search: $this->eol, replace: "\0PHP_EOL\0", subject: $string);
        $string = (string) preg_replace(pattern: "/(\r\n|\n|\r)/", replacement: $this->eol, subject: $string);
        return str_replace(search: "\0PHP_EOL\0", replace: $this->eol, subject: $string);
    }
}
