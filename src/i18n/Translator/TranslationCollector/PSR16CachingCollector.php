<?php

declare(strict_types=1);

namespace Jield\I18n\Translator\TranslationCollector;

use Jield\I18n\Exception\RuntimeException;
use Jield\I18n\Translator\TextDomain;
use Psr\SimpleCache\CacheInterface;

use function get_debug_type;
use function sprintf;

final readonly class PSR16CachingCollector implements CachingCollectorInterface
{
    private const string DEFAULT_PREFIX = 'JieldTranslations';
    /** @var non-empty-string */
    private string $keyPrefix;

    /** @param non-empty-string|null $keyPrefix */
    public function __construct(
        private CacheInterface $cache,
        private TranslationCollectorInterface $collector,
        string|null $keyPrefix = null,
    ) {
        $this->keyPrefix = $keyPrefix ?? self::DEFAULT_PREFIX;
    }

    public function collect(string $textDomain, string $locale): TextDomain
    {
        $key = $this->cacheKey(textDomain: $textDomain, locale: $locale);
        if ($this->cache->has(key: $key)) {
            return $this->assertTextDomain(key: $key, value: $this->cache->get(key: $key));
        }

        $data = $this->collector->collect(textDomain: $textDomain, locale: $locale);

        $this->cache->set(key: $key, value: $data);

        return $data;
    }

    public function cacheKey(string $textDomain, string $locale): string
    {
        return sprintf('%s-%s-%s', $this->keyPrefix, $textDomain, $locale);
    }

    public function clearCache(string $textDomain, string $locale): void
    {
        $this->cache->delete(key: $this->cacheKey(textDomain: $textDomain, locale: $locale));
    }

    private function assertTextDomain(string $key, mixed $value): TextDomain
    {
        if (! $value instanceof TextDomain) {
            throw new RuntimeException(message: sprintf(
                'Expected the cache key "%s" to contain translation messages but it was "%s"',
                $key,
                get_debug_type(value: $value),
            ));
        }

        return $value;
    }
}
