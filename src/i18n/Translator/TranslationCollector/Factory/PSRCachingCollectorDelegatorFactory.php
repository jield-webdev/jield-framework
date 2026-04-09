<?php

declare(strict_types=1);

namespace Jield\I18n\Translator\TranslationCollector\Factory;

use Jield\I18n\Exception\InvalidArgumentException;
use Jield\I18n\Exception\RuntimeException;
use Jield\I18n\Translator\TranslationCollector\PSR16CachingCollector;
use Jield\I18n\Translator\TranslationCollector\PSR6CachingCollector;
use Jield\I18n\Translator\TranslationCollector\TranslationCollectorInterface;
use Laminas\ServiceManager\Factory\DelegatorFactoryInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;

use function assert;
use function get_debug_type;
use function is_array;
use function is_iterable;
use function is_string;
use function iterator_to_array;
use function sprintf;

/**
 * @internal
 *
 * @psalm-internal Jield\I18n
 * @psalm-internal JieldTest\I18n
 */
final readonly class PSRCachingCollectorDelegatorFactory implements DelegatorFactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        string $name,
        callable $callback,
        ?array $options = null,
    ): TranslationCollectorInterface {
        $collector = $callback();
        if (! $collector instanceof TranslationCollectorInterface) {
            throw new RuntimeException(message: sprintf(
                'Expected the delegated service to be a translation collector but received "%s"',
                get_debug_type(value: $collector),
            ));
        }

        /** @psalm-var mixed $config */
        $config = $container->has(id: 'config') ? $container->get('config') : [];
        $config = is_iterable(value: $config) ? iterator_to_array(iterator: $config) : [];

        $i18n = $config['Jield-i18n'] ?? [];
        assert(assertion: is_array(value: $i18n));

        $translator = $i18n['translator'] ?? [];
        assert(assertion: is_array(value: $translator));

        $cache
            = $this->getPsr6Cache(options: $translator, container: $container)
            ?? $this->getPsr16Cache(options: $translator, container: $container);

        if ($cache === null) {
            return $collector;
        }

        /** @psalm-var mixed $prefix */
        $prefix = $translator['cache_key_prefix'] ?? null;
        if ($prefix !== null && (! is_string(value: $prefix) || $prefix === '')) {
            throw new InvalidArgumentException(
                message: 'The `cache_key_prefix` option must resolve to null or a non-empty-string',
            );
        }

        /** @psalm-var non-empty-string|null $prefix */

        return $cache instanceof CacheItemPoolInterface
            ? new PSR6CachingCollector(
                cache: $cache,
                collector: $collector,
                keyPrefix: $prefix,
            )
            : new PSR16CachingCollector(
                cache: $cache,
                collector: $collector,
                keyPrefix: $prefix,
            );
    }

    private function getPsr6Cache(array $options, ContainerInterface $container): CacheItemPoolInterface|null
    {
        /** @psalm-var mixed $cacheService */
        $cacheService = $options['psr6_cache'] ?? null;
        if (! is_string(value: $cacheService) || ! $container->has(id: $cacheService)) {
            return null;
        }

        $cache = $container->get($cacheService);
        if (! $cache instanceof CacheItemPoolInterface) {
            return null;
        }

        return $cache;
    }

    private function getPsr16Cache(array $options, ContainerInterface $container): CacheInterface|null
    {
        /** @psalm-var mixed $cacheService */
        $cacheService = $options['psr16_cache'] ?? null;
        if (! is_string(value: $cacheService) || ! $container->has(id: $cacheService)) {
            return null;
        }

        $cache = $container->get($cacheService);
        if (! $cache instanceof CacheInterface) {
            return null;
        }

        return $cache;
    }
}
