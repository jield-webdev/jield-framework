<?php

declare(strict_types=1);

namespace Jield\I18n;

use Jield\I18n\Exception\InvalidArgumentException;
use Locale;

use function assert;
use function preg_match;
use function strtoupper;

/**
 * @psalm-immutable
 */
final readonly class CountryCode
{
    /** @param non-empty-string $code */
    private function __construct(private string $code)
    {
    }

    /** @return non-empty-string */
    public function toString(): string
    {
        return $this->code;
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }

    /**
     * Create a new ValueObject from an ISO 3166 Country Code
     * Country codes are 2 letter, uppercase strings representing a country identifier on planet earth. The given
     * value must also represent a country known by PHP’s intl extension.
     * Valid values include 'US', 'GB', 'ZA', 'FR' etc.
     *
     * @link https://en.wikipedia.org/wiki/List_of_ISO_3166_country_codes
     *
     * @param non-empty-string $code
     * @throws InvalidArgumentException An invalid string or an unknown country will cause an exception.
     * @psalm-pure
     */
    public static function fromString(string $code): self
    {
        $code = strtoupper(string: $code);
        if (! preg_match(pattern: '/^[A-Z]{2}$/', subject: $code)) {
            throw InvalidArgumentException::withInvalidCountryCode(received: $code);
        }

        $displayName = Locale::getDisplayRegion(locale: '-' . $code, displayLocale: 'GB');
        if ($displayName === '' || $displayName === 'Unknown Region') {
            throw InvalidArgumentException::withUnknownCountryCode(code: $code);
        }

        return new self(code: $code);
    }

    /**
     * Create a new value object from a locale string
     *
     * Given a well-formed locale, this method will extract the relevant country code and proxy to @link fromString
     * Valid values include: 'en_GB', 'en-GB', 'zh-Hans-CN'
     *
     * @param non-empty-string $locale
     * @throws InvalidArgumentException An unrecognizable locale will cause an exception.
     * @psalm-pure
     */
    public static function fromLocaleString(string $locale): self
    {
        $region = Locale::getRegion(locale: $locale);
        if ($region === null || $region === '') {
            throw InvalidArgumentException::withUnrecognizableLocaleString(locale: $locale);
        }

        return self::fromString(code: $region);
    }

    /**
     * Return a country code from either a string code or a locale string falling back to the system locale if null
     *
     * @link fromLocaleString
     * @link fromString
     *
     * @throws InvalidArgumentException When a non-empty string is provided that cannot be recognized,
     *                                  an exception will be thrown.
     */
    public static function detect(string|self|null $countryCodeOrLocale = null): self
    {
        if ($countryCodeOrLocale instanceof self) {
            return $countryCodeOrLocale;
        }

        if ($countryCodeOrLocale === null || $countryCodeOrLocale === '') {
            $countryCodeOrLocale = Locale::getDefault();
        }

        assert(assertion: $countryCodeOrLocale !== '');

        $code = self::tryFromString(countryCodeOrLocale: $countryCodeOrLocale);
        if ($code) {
            return $code;
        }

        throw InvalidArgumentException::withUndetectableCountryCode(localeOrCode: $countryCodeOrLocale);
    }

    /**
     * Attempt to create a value object from either a country code or a locale string
     *
     * This method returns null if the input cannot be recognized as either a code or a locale.
     *
     * @link fromLocaleString
     * @link fromString
     *
     * @param non-empty-string $countryCodeOrLocale
     * @psalm-pure
     */
    public static function tryFromString(string $countryCodeOrLocale): ?self
    {
        try {
            return self::fromLocaleString(locale: $countryCodeOrLocale);
        } catch (InvalidArgumentException) {
        }

        try {
            return self::fromString(code: $countryCodeOrLocale);
        } catch (InvalidArgumentException) {
        }

        return null;
    }
}
