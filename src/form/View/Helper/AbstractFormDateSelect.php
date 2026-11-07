<?php

declare(strict_types=1);

namespace Jield\Form\View\Helper;

use DateTime;
use IntlDateFormatter;
use Jield\Form\Exception;
use Locale;

use function assert;
use function extension_loaded;
use function preg_split;
use function sprintf;
use function str_replace;
use function stripos;

use const PREG_SPLIT_DELIM_CAPTURE;
use const PREG_SPLIT_NO_EMPTY;

abstract class AbstractFormDateSelect extends AbstractHelper
{
    /**
     * FormSelect helper
     *
     * @var null|FormSelect
     */
    protected ?FormSelect $selectHelper = null;

    /**
     * Date formatter to use
     *
     * @var int
     */
    protected int $dateType;

    /**
     * Pattern to use for Date rendering
     *
     * @var null|string
     */
    protected ?string $pattern = null;

    /**
     * Locale to use
     *
     * @var null|string
     */
    protected ?string $locale = null;

    /**
     * @throws Exception\ExtensionNotLoadedException If ext/intl is not present.
     */
    public function __construct()
    {
        if (!extension_loaded(extension: 'intl')) {
            throw new Exception\ExtensionNotLoadedException(message: sprintf(
                '%s component requires the intl PHP extension',
                __NAMESPACE__,
            ));
        }

        // Delaying initialization until we know ext/intl is available
        $this->dateType = IntlDateFormatter::LONG;
    }

    /**
     * Parse the pattern
     */
    protected function parsePattern(bool $renderDelimiters = true): array
    {
        $pattern = $this->getPattern();
        $pregResult = preg_split(
            pattern: "/([ \-,.\/]*(?:'[a-zA-Z]+')*[ \-,.\/]+)/",
            subject: $pattern,
            limit: -1,
            flags: PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
        );
        assert(assertion: $pregResult !== false);

        $result = [];
        foreach ($pregResult as $value) {
            $noDelimiter = stripos(haystack: $value, needle: "'") === false;
            if ($noDelimiter && stripos(haystack: $value, needle: 'd') !== false) {
                $result['day'] = $value;
            } elseif ($noDelimiter && stripos(haystack: $value, needle: 'm') !== false) {
                $result['month'] = $value;
            } elseif ($noDelimiter && stripos(haystack: $value, needle: 'y') !== false) {
                $result['year'] = $value;
            } elseif ($renderDelimiters) {
                $result[] = str_replace(search: "'", replace: '', subject: $value);
            }
        }

        return $result;
    }

    /**
     * Retrieve pattern to use for Date rendering
     */
    public function getPattern(): string
    {
        if (null === $this->pattern) {
            $intl = new IntlDateFormatter(
                locale: $this->getLocale(),
                dateType: $this->dateType,
                timeType: IntlDateFormatter::NONE,
            );
            $this->pattern = $intl->getPattern();
        }

        return $this->pattern;
    }

    /**
     * Set date formatter
     *
     * @return static
     */
    public function setDateType(int $dateType): static
    {
        // The FULL format uses values that are not used
        if ($dateType === IntlDateFormatter::FULL) {
            $dateType = IntlDateFormatter::LONG;
        }

        if ($this->dateType !== $dateType) {
            $this->pattern = null;
        }

        $this->dateType = $dateType;

        return $this;
    }

    /**
     * Get date formatter
     */
    public function getDateType(): int
    {
        return $this->dateType;
    }

    /**
     * Set locale
     *
     * @return static
     */
    public function setLocale(string $locale): static
    {
        if ($this->locale !== $locale) {
            $this->pattern = null;
        }

        $this->locale = $locale;

        return $this;
    }

    /**
     * Get locale
     */
    public function getLocale(): string
    {
        $this->locale ??= Locale::getDefault();

        return $this->locale;
    }

    /**
     * Create a key => value options for months
     *
     * @param string $pattern Pattern to use for months
     * @return array<array-key, string|false>
     */
    protected function getMonthsOptions(string $pattern): array
    {
        $valueFormatter = new IntlDateFormatter(
            locale: $this->getLocale(),
            dateType: IntlDateFormatter::NONE,
            timeType: IntlDateFormatter::NONE,
            timezone: null,
            calendar: null,
            pattern: $pattern,
        );
        $date = new DateTime(datetime: '1970-01-01');

        $result = [];
        for ($month = 1; $month <= 12; $month++) {
            $key = $date->format(format: 'm');
            $value = $valueFormatter->format(datetime: $date->getTimestamp());
            $result[$key] = $value;

            $date->modify(modifier: '+1 month');
        }

        return $result;
    }

    /**
     * Create a key => value options for years
     * NOTE: we don't use a pattern for years, as years written as two digits can lead to hard to
     * read date for users, so we only use four digits years
     *
     * @return array<int, string>
     */
    protected function getYearsOptions(int $minYear, int $maxYear): array
    {
        $result = [];
        for ($i = $maxYear; $i >= $minYear; --$i) {
            $result[$i] = (string) $i;
        }

        return $result;
    }

    /**
     * Retrieve the FormSelect helper
     */
    protected function getSelectElementHelper(): FormSelect
    {
        if (null !== $this->selectHelper) {
            return $this->selectHelper;
        }

        if (null !== $this->view) {
            $this->selectHelper = $this->getView()->plugin('formselect', \Jield\Form\View\Helper\FormSelect::class);
        }

        $this->selectHelper ??= new FormSelect();

        return $this->selectHelper;
    }
}
