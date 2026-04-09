<?php

declare(strict_types=1);

namespace Jield\I18n\Translator\Value;

use Jield\I18n\Exception\InvalidArgumentException;
use Jield\I18n\Translator\Loader\FileLoaderInterface;

use function is_string;
use function sprintf;

/**
 * @internal
 *
 * @psalm-internal Jield
 * @psalm-internal JieldTest
 * @psalm-type TranslationFileSpec = array{
 *     type: non-empty-string|class-string<FileLoaderInterface>,
 *     filename: non-empty-string,
 *     locale?: non-empty-string,
 *     text_domain?: non-empty-string,
 * }
 */
final readonly class TranslationFile
{
    /**
     * @param non-empty-string|class-string<FileLoaderInterface> $type The type of file loader that should be used
     *                                                                 to load this file
     * @param non-empty-string $filename
     * @param non-empty-string $locale
     * @param non-empty-string $textDomain
     */
    public function __construct(
        public string $type,
        public string $filename,
        public string $locale,
        public string $textDomain,
    ) {
    }

    /**
     * @param array<array-key, mixed> $spec
     * @param non-empty-string $defaultLocale
     * @param non-empty-string $defaultTextDomain
     */
    public static function fromArray(array $spec, string $defaultLocale, string $defaultTextDomain): self
    {
        return new self(
            type: self::assertKey(value: $spec['type'] ?? null, key: 'type'),
            filename: self::assertKey(value: $spec['filename'] ?? null, key: 'filename'),
            locale: self::assertKey(value: $spec['locale'] ?? $defaultLocale, key: 'locale'),
            textDomain: self::assertKey(value: $spec['text_domain'] ?? $defaultTextDomain, key: 'text_domain'),
        );
    }

    /** @return non-empty-string */
    private static function assertKey(mixed $value, string $key): string
    {
        if (! is_string(value: $value) || $value === '') {
            throw new InvalidArgumentException(message: sprintf(
                'The key "%s" must be set and should contain a non-empty string',
                $key,
            ));
        }

        return $value;
    }
}
