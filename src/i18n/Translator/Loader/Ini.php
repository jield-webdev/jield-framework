<?php

declare(strict_types=1);

namespace Jield\I18n\Translator\Loader;

use Jield\I18n\Exception\InvalidArgumentException;
use Jield\I18n\Translator\Plural\Rule as PluralRule;
use Jield\I18n\Translator\TextDomain;

use function array_shift;
use function count;
use function is_array;
use function is_file;
use function is_readable;
use function is_string;
use function sprintf;
use function stream_resolve_include_path;

/**
 * A translation file loader for files in PHP's ini format
 */
final readonly class Ini implements FileLoaderInterface
{
    public function __construct(private IniFileReader $fileReader)
    {
    }

    public function load(string $locale, string $filename): TextDomain|null
    {
        $resolvedIncludePath = stream_resolve_include_path(filename: $filename);
        $fromIncludePath     = $resolvedIncludePath !== false ? $resolvedIncludePath : $filename;
        if (! $fromIncludePath || ! is_file(filename: $fromIncludePath) || ! is_readable(filename: $fromIncludePath)) {
            throw new InvalidArgumentException(message: sprintf(
                'Could not find or open file %s for reading',
                $filename,
            ));
        }

        $messages           = [];
        $messagesNamespaced = $this->fileReader->read(filename: $fromIncludePath);

        $list = $messagesNamespaced;
        if (isset($messagesNamespaced['translation']) && is_array(value: $messagesNamespaced['translation'])) {
            $list = $messagesNamespaced['translation'];
        }

        foreach ($list as $message) {
            if (! is_array(value: $message) || count(value: $message) < 2) {
                throw new InvalidArgumentException(
                    message: 'Each INI row must be an array with message and translation',
                );
            }

            /** @psalm-var mixed $key */
            $key = $message['message'] ?? null;
            /** @psalm-var mixed $value */
            $value = $message['translation'] ?? null;

            if (is_string(value: $key) && is_string(value: $value)) {
                $messages[$key] = $value;
                continue;
            }

            /** @psalm-var mixed $key */
            $key = array_shift(array: $message);
            /** @psalm-var mixed $value */
            $value = array_shift(array: $message);

            if (is_string(value: $key) && is_string(value: $value)) {
                $messages[$key] = $value;
            }
        }

        $textDomain = new TextDomain(array: $messages);

        /** @psalm-var mixed $pluralForms */
        $pluralForms = $messagesNamespaced['plural']['plural_forms'] ?? null;

        if (is_string(value: $pluralForms)) {
            $textDomain->setPluralRule(
                rule: PluralRule::fromString(string: $pluralForms),
            );
        }

        return $textDomain;
    }
}
