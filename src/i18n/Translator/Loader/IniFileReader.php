<?php

declare(strict_types=1);

namespace Jield\I18n\Translator\Loader;

use Jield\I18n\Exception\RuntimeException;

use function array_filter;
use function array_merge_recursive;
use function array_shift;
use function assert;
use function count;
use function explode;
use function is_array;
use function is_file;
use function is_readable;
use function is_string;
use function parse_ini_file;
use function parse_ini_string;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function str_contains;

use const E_WARNING;
use const INI_SCANNER_NORMAL;
use const INI_SCANNER_TYPED;

final readonly class IniFileReader
{
    /** @param non-empty-string $nestSeparator */
    public function __construct(
        private string $nestSeparator = '.',
        private bool $processSections = true,
        private bool $typed = false,
    ) {
    }

    /**
     * @param non-empty-string $filename
     * @return array<array-key, mixed>
     * @throws \RuntimeException
     */
    public function read(string $filename): array
    {
        if (! is_file(filename: $filename) || ! is_readable(filename: $filename)) {
            throw new RuntimeException(message: sprintf(
                "File '%s' doesn't exist or is not readable",
                $filename,
            ));
        }

        set_error_handler(
            callback: function ($error, $message = '') {
                throw new RuntimeException(
                    message: sprintf('Error reading INI file: %s', $message),
                    code: $error,
                );
            },
            error_levels: E_WARNING,
        );

        try {
            $ini = parse_ini_file(
                filename: $filename,
                process_sections: $this->processSections,
                scanner_mode: $this->typed ? INI_SCANNER_TYPED : INI_SCANNER_NORMAL,
            );
        } finally {
            restore_error_handler();
        }

        assert(assertion: $ini !== false);

        return $this->process(data: $ini);
    }

    /**
     * @return array<array-key, mixed>
     * @throws \RuntimeException
     */
    public function readString(string $string): array
    {
        if (empty($string)) {
            return [];
        }

        set_error_handler(
            callback: function ($error, $message = '') {
                throw new RuntimeException(
                    message: sprintf('Error reading INI string: %s', $message),
                    code: $error,
                );
            },
            error_levels: E_WARNING,
        );

        try {
            $ini = parse_ini_string(
                ini_string: $string,
                process_sections: $this->processSections,
                scanner_mode: $this->typed ? INI_SCANNER_TYPED : INI_SCANNER_NORMAL,
            );
        } finally {
            restore_error_handler();
        }

        if ($ini === false) {
            throw new RuntimeException(message: 'The data could not be parsed as an ini file');
        }

        return $this->process(data: $ini);
    }

    /**
     * Process data from the parsed ini file.
     *
     * @param  array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    private function process(array $data): array
    {
        $config = [];

        foreach ($data as $section => $value) {
            if (! is_array(value: $value)) {
                $this->processKey(key: $section, value: $value, config: $config);

                continue;
            }

            if (is_string(value: $section) && str_contains(haystack: $section, needle: $this->nestSeparator)) {
                $sections = explode(separator: $this->nestSeparator, string: $section);
                $config   = array_merge_recursive($config, $this->buildNestedSection(sections: $sections, value: $value));

                continue;
            }

            $config[$section] = $this->processSection(section: $value);
        }

        return $config;
    }

    /**
     * Process a nested section
     *
     * @param list<string> $sections
     * @param array<array-key, mixed> $value
     * @return array<array-key, mixed>
     */
    private function buildNestedSection(array $sections, array $value): array
    {
        if (count(value: $sections) === 0) {
            return $this->processSection(section: $value);
        }

        $nestedSection = [];

        $first                 = array_shift(array: $sections);
        $nestedSection[$first] = $this->buildNestedSection(sections: $sections, value: $value);

        return $nestedSection;
    }

    /**
     * Process a section
     *
     * @param array<array-key, mixed> $section>
     * @return array<array-key, mixed>
     */
    protected function processSection(array $section): array
    {
        $config = [];

        /** @psalm-var mixed $value */
        foreach ($section as $key => $value) {
            $this->processKey(key: $key, value: $value, config: $config);
        }

        return $config;
    }

    /**
     * Process a key.
     *
     * @throws \RuntimeException
     */
    protected function processKey(int|string $key, mixed $value, array &$config): void
    {
        $key = (string) $key;
        if (str_contains(haystack: $key, needle: $this->nestSeparator)) {
            $pieces = explode(separator: $this->nestSeparator, string: $key, limit: 2);

            $nonEmpty = array_filter(array: $pieces, callback: static fn (string $item): bool => $item !== '');

            if (count(value: $nonEmpty) !== 2) {
                throw new RuntimeException(message: sprintf('Invalid key "%s"', $key));
            }

            assert(assertion: count(value: $pieces) === 2);

            if (! isset($config[$pieces[0]])) {
                if ($pieces[0] === '0' && ! empty($config)) {
                    $config = [$pieces[0] => $config];
                } else {
                    $config[$pieces[0]] = [];
                }
            }

            assert(assertion: is_array(value: $config[$pieces[0]]));

            /** @psalm-suppress MixedArgument */
            $this->processKey(key: $pieces[1], value: $value, config: $config[$pieces[0]]);

            return;
        }

        /** @psalm-suppress MixedAssignment */
        $config[$key] = $value;
    }
}
