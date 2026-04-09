<?php

declare(strict_types=1);

namespace Jield\I18n\Factory;

use DateTimeZone;
use Jield\I18n\CountryCode;
use Jield\I18n\DefaultLocale;
use Jield\I18n\I18nDefaults;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\Translator\TranslatorInterface;
use Locale;
use NumberFormatter;
use Psr\Container\ContainerInterface;

use function assert;
use function date_default_timezone_get;
use function is_array;
use function is_iterable;
use function is_string;
use function iterator_to_array;
use function preg_match;

/**
 * @internal
 *
 * @psalm-internal Jield\I18n
 * @psalm-internal JieldTest\I18n
 */
final readonly class I18nDefaultsFactory implements FactoryInterface
{
    /** @psalm-suppress MixedAssignment */
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): I18nDefaults {
        $locale = $container->get(DefaultLocale::class);

        /** @psalm-var mixed $config */
        $config = $container->has(id: 'config') ? $container->get('config') : [];
        $config = is_iterable(value: $config) ? iterator_to_array(iterator: $config) : [];

        $i18nDefaults = $config['Jield-i18n'] ?? [];
        $i18nDefaults = is_array(value: $i18nDefaults) ? $i18nDefaults : [];

        /**
         * Timezone has historically been at the top-level, but we'll look under the new top-level key too
         */
        $timezone = $config['timezone'] ?? null;
        $timezone = $i18nDefaults['defaultTimeZone'] ?? $timezone;
        $timezone = is_string(value: $timezone) && $timezone !== '' ? $timezone : date_default_timezone_get();

        $currency = $i18nDefaults['defaultCurrency'] ?? null;
        $currency = ! is_string(value: $currency) || $currency === ''
            ? $this->determineCurrencyFromLocale(locale: $locale->locale)
            : $currency;

        $country = $i18nDefaults['defaultCountry'] ?? Locale::getRegion(locale: $locale->locale);
        assert(assertion: is_string(value: $country) && $country !== '');

        $textDomain = $i18nDefaults['defaultTextDomain'] ?? null;
        $textDomain = ! is_string(value: $textDomain) || $textDomain === ''
            ? TranslatorInterface::DEFAULT_TEXT_DOMAIN
            : $textDomain;

        return new I18nDefaults(
            defaultTimeZone: new DateTimeZone(timezone: $timezone),
            defaultCurrencyCode: $currency,
            defaultTextDomain: $textDomain,
            defaultLocale: $locale->locale,
            defaultCountry: CountryCode::fromString(code: $country),
        );
    }

    /** @return non-empty-string */
    private function determineCurrencyFromLocale(string $locale): string
    {
        $formatter = new NumberFormatter(locale: $locale, style: NumberFormatter::CURRENCY);
        $formatter->setPattern(pattern: '¤¤');
        $format = $formatter->format(num: 0);
        preg_match(pattern: '/([A-Z]{3})/', subject: $format, matches: $match);
        $code = $match[1] ?? null;

        assert(assertion: is_string(value: $code) && $code !== '');

        return $code;
    }
}
