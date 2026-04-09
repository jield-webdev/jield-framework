<?php

declare(strict_types=1);

namespace Jield\I18n\Translator;

use Jield\I18n\I18nDefaults;
use Jield\I18n\Translator\TranslationCollector\TranslationCollectorInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

use function assert;
use function is_array;
use function is_bool;
use function is_iterable;
use function is_string;
use function iterator_to_array;

/**
 * @internal
 *
 * @psalm-internal Jield\I18n
 * @psalm-internal JieldTest\I18n
 */
final readonly class TranslatorServiceFactory implements FactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): Translator {
        $defaults = $container->get(I18nDefaults::class);

        /**
         * Determine the default locale, allowing build-time locale to override configuration
         *
         * @psalm-var mixed $locale
         */
        $locale = $options['locale'] ?? null;
        $locale = is_string(value: $locale) && $locale !== '' ? $locale : $defaults->defaultLocale;

        /** @psalm-var mixed $config */
        $config = $container->has(id: 'config') ? $container->get('config') : [];
        $config = is_iterable(value: $config) ? iterator_to_array(iterator: $config) : [];

        $i18n = $config['Jield-i18n'] ?? [];
        assert(assertion: is_array(value: $i18n));

        $translator = $i18n['translator'] ?? [];
        assert(assertion: is_array(value: $translator));

        /**
         * The fallback locale is optionally used to provide translations when none are available in the default, or
         * runtime locale.
         *
         * @psalm-var mixed $fallbackLocale
         */
        $fallbackLocale = $translator['fallback_locale'] ?? null;
        $fallbackLocale = is_string(value: $fallbackLocale) && $fallbackLocale !== '' ? $fallbackLocale : null;

        /** @psalm-var mixed $enableEvents */
        $enableEvents = $translator['event_manager_enabled'] ?? false;
        $enableEvents = is_bool(value: $enableEvents) && $enableEvents;

        $instance = new Translator(
            collector: $container->get(TranslationCollectorInterface::class),
            defaultLocale: $locale,
            fallbackLocale: $fallbackLocale,
            defaultTextDomain: $defaults->defaultTextDomain,
        );

        if ($enableEvents) {
            $instance->enableEventManager();
        }

        return $instance;
    }
}
