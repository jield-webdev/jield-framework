<?php

declare(strict_types=1);

namespace Jield\I18n\Translator\Loader;

use Jield\I18n\Exception;
use Jield\I18n\Translator\Plural\Rule as PluralRule;
use Jield\I18n\Translator\TextDomain;

use function gettype;
use function is_array;
use function is_file;
use function is_readable;
use function is_string;
use function sprintf;
use function stream_resolve_include_path;

/**
 * PHP array loader.
 */
final readonly class PhpArray extends AbstractFileLoader
{
    /**
     * @throws Exception\InvalidArgumentException
     */
    public function load(string $locale, string $filename): TextDomain|null
    {
        $resolvedIncludePath = stream_resolve_include_path(filename: $filename);
        $fromIncludePath     = $resolvedIncludePath !== false ? $resolvedIncludePath : $filename;
        if (! $fromIncludePath || ! is_file(filename: $fromIncludePath) || ! is_readable(filename: $fromIncludePath)) {
            throw new Exception\InvalidArgumentException(message: sprintf(
                'Could not find or open file %s for reading',
                $filename
            ));
        }

        /** @psalm-suppress UnresolvableInclude */
        $messages = include $fromIncludePath;

        if (! is_array(value: $messages)) {
            throw new Exception\InvalidArgumentException(message: sprintf(
                'Expected an array, but received %s',
                gettype(value: $messages)
            ));
        }

        /** @var mixed $pluralForms */
        $pluralForms = $messages['']['plural_forms'] ?? null;
        unset($messages['']);

        $textDomain = new TextDomain(array: $messages);

        if (is_string(value: $pluralForms) && $pluralForms !== '') {
            $textDomain->setPluralRule(
                rule: PluralRule::fromString(string: $pluralForms),
            );
        }

        return $textDomain;
    }
}
