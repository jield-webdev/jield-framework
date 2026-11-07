<?php

declare(strict_types=1);

namespace Jield\Form\View\Helper;

use DateTime;
use IntlDateFormatter;
use Jield\Form\Element\DateTimeSelect as DateTimeSelectElement;
use Jield\Form\ElementInterface;
use Jield\Form\Exception;
use Jield\Stdlib\Value;

use function array_diff;
use function array_intersect;
use function array_key_exists;
use function array_keys;
use function assert;
use function implode;
use function is_numeric;
use function preg_match_all;
use function preg_replace;
use function preg_split;
use function rtrim;
use function sprintf;
use function str_contains;
use function str_replace;
use function stripos;
use function trim;

use const PREG_SPLIT_DELIM_CAPTURE;
use const PREG_SPLIT_NO_EMPTY;

/** @final */
class FormDateTimeSelect extends AbstractFormDateSelect
{
    /**
     * Time formatter to use
     *
     * @var int
     */
    protected int $timeType;

    /**
     * @throws Exception\ExtensionNotLoadedException If ext/intl is not present.
     */
    public function __construct()
    {
        parent::__construct();

        // Delaying initialization until we know ext/intl is available
        $this->timeType = IntlDateFormatter::LONG;
    }

    /**
     * Invoke helper as function
     *
     * Proxies to {@link render()}.
     *
     * @template T as null|ElementInterface
     * @psalm-param T $element
     * @psalm-return (T is null ? static : string)
     * @return string|self
     */
    public function __invoke(
        ?ElementInterface $element = null,
        int $dateType = IntlDateFormatter::LONG,
        int $timeType = IntlDateFormatter::LONG,
        ?string $locale = null,
    ): string|static {
        if (!$element) {
            return $this;
        }

        $this->setDateType(dateType: $dateType);
        $this->setTimeType(timeType: $timeType);

        if ($locale !== null) {
            $this->setLocale(locale: $locale);
        }

        return $this->render(element: $element);
    }

    /**
     * Render a date element that is composed of six selects
     *
     * @throws Exception\InvalidArgumentException
     * @throws Exception\DomainException
     */
    public function render(ElementInterface $element): string
    {
        if (!$element instanceof DateTimeSelectElement) {
            throw new Exception\InvalidArgumentException(message: sprintf(
                '%s requires that the element is of type Jield\Form\Element\DateTimeSelect',
                __METHOD__,
            ));
        }

        $name = $element->getName();
        if ($name === null || $name === '') {
            throw new Exception\DomainException(message: sprintf(
                '%s requires that the element has an assigned name; none discovered',
                __METHOD__,
            ));
        }

        $shouldRenderDelimiters = $element->shouldRenderDelimiters();
        $selectHelper = $this->getSelectElementHelper();
        $pattern = $this->parsePattern(renderDelimiters: $shouldRenderDelimiters);
        $daysOptions = $this->getDaysOptions(pattern: \Webmozart\Assert\Assert::string($pattern['day']));
        $monthsOptions = $this->getMonthsOptions(pattern: \Webmozart\Assert\Assert::string($pattern['month']));
        $yearOptions = $this->getYearsOptions(minYear: $element->getMinYear(), maxYear: $element->getMaxYear());
        $hourOptions = $this->getHoursOptions(pattern: \Webmozart\Assert\Assert::string($pattern['hour']));
        $minuteOptions = $this->getMinutesOptions(pattern: \Webmozart\Assert\Assert::string($pattern['minute']));

        $dayElement = $element->getDayElement()->setValueOptions(options: $daysOptions);
        $monthElement = $element->getMonthElement()->setValueOptions(options: $monthsOptions);
        $yearElement = $element->getYearElement()->setValueOptions(options: $yearOptions);
        $hourElement = $element->getHourElement()->setValueOptions(options: $hourOptions);
        $minuteElement = $element->getMinuteElement()->setValueOptions(options: $minuteOptions);

        if ($element->shouldCreateEmptyOption()) {
            $dayElement->setEmptyOption(emptyOption: '');
            $yearElement->setEmptyOption(emptyOption: '');
            $monthElement->setEmptyOption(emptyOption: '');
            $hourElement->setEmptyOption(emptyOption: '');
            $minuteElement->setEmptyOption(emptyOption: '');
        }

        $data = [];
        $data[$pattern['day']] = $selectHelper->render(element: $dayElement);
        $data[$pattern['month']] = $selectHelper->render(element: $monthElement);
        $data[Value::key($pattern['year'])] = $selectHelper->render(element: $yearElement);
        $data[$pattern['hour']] = $selectHelper->render(element: $hourElement);
        $data[$pattern['minute']] = $selectHelper->render(element: $minuteElement);

        if ($element->shouldShowSeconds() && array_key_exists(key: 'second', array: $pattern)) {
            $secondOptions = $this->getSecondsOptions(pattern: \Webmozart\Assert\Assert::string($pattern['second']));
            $secondElement = $element->getSecondElement()->setValueOptions(options: $secondOptions);

            if ($element->shouldCreateEmptyOption()) {
                $secondElement->setEmptyOption(emptyOption: '');
            }

            $data[$pattern['second']] = $selectHelper->render(element: $secondElement);
        } else {
            unset($pattern['second']);

            if ($shouldRenderDelimiters) {
                unset($pattern[4]);
            }
        }

        $markup = '';
        /** @mago-var mixed $value */
        foreach ($pattern as $key => $value) {
            // Delimiter
            if (is_numeric(value: $key)) {
                $markup .= Value::stringify($value);
            } else {
                $markup .= $data[$value];
            }
        }

        return trim(string: $markup);
    }

    /**
     * @return static
     */
    public function setTimeType(int $timeType): static
    {
        // The FULL format uses values that are not used
        if ($timeType === IntlDateFormatter::FULL) {
            $timeType = IntlDateFormatter::LONG;
        }

        if ($this->timeType !== $timeType) {
            $this->pattern = null;
        }

        $this->timeType = $timeType;

        return $this;
    }

    public function getTimeType(): int
    {
        return $this->timeType;
    }

    /**
     * Override to also get time part
     */
    public function getPattern(): string
    {
        if ($this->pattern === null) {
            $intl = new IntlDateFormatter(
                locale: $this->getLocale(),
                dateType: $this->dateType,
                timeType: $this->timeType,
            );
            // remove time zone format character
            $pattern = rtrim(string: $intl->getPattern(), characters: ' z');
            $this->pattern = $pattern;
        }

        return $this->pattern;
    }

    /**
     * Parse the pattern
     */
    protected function parsePattern(bool $renderDelimiters = true): array
    {
        $matches = [];
        $pattern = $this->getPattern();

        // are there any non-latin characters in the pattern?
        $count = preg_match_all(pattern: '/[^\p{Latin}\s\-,.:\/\\\'\[\]\(\)]+/u', subject: $pattern, matches: $matches);

        if ($count) {
            // put single quotes around these characters
            foreach ($matches[0] as $match) {
                $pattern = str_replace(search: $match, replace: "'{$match}'", subject: $pattern);
            }

            // remove double quotes, if there were already quotes around them
            $pattern = str_replace(search: "''", replace: "'", subject: $pattern);
        }

        $pregResult = preg_split(
            pattern: "/([ \-,.:0abvxz\/]*'.*?'[ \-,.:0abvxz\/]*)|([ \-,.:0abvxz\/]+)/i",
            subject: $pattern,
            limit: -1,
            flags: PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
        );

        assert(assertion: $pregResult !== false);

        $result = [];
        foreach ($pregResult as $value) {
            $noDelimiter = str_contains(haystack: $value, needle: "'") === false;

            if ($noDelimiter && stripos(haystack: $value, needle: 'd') !== false) {
                $result['day'] = $value;
            } elseif ($noDelimiter && str_contains(haystack: $value, needle: 'M')) {
                $result['month'] = $value;
            } elseif ($noDelimiter && stripos(haystack: $value, needle: 'y') !== false) {
                $result['year'] = $value;
            } elseif ($noDelimiter && stripos(haystack: $value, needle: 'h') !== false) {
                $result['hour'] = $value;
            } elseif ($noDelimiter && str_contains(haystack: $value, needle: 'm')) {
                $result['minute'] = $value;
            } elseif ($noDelimiter && str_contains(haystack: $value, needle: 's')) {
                $result['second'] = $value;
            } elseif ($renderDelimiters) {
                $result[] = preg_replace(pattern: "/['0abvxz[\]]/i", replacement: '', subject: $value);
            }
        }

        $valid = ['day', 'month', 'year', 'hour', 'minute'];
        if (array_intersect($valid, array_keys(array: $result)) !== $valid) {
            throw new Exception\IntlException(message: sprintf(
                "Cannot parse date format '%s' for locale '%s': missing '%s'",
                $pattern,
                $this->getLocale(),
                implode(separator: "', '", array: array_diff($valid, array_keys(array: $result))),
            ));
        }

        return $result;
    }

    /**
     * Create a key => value options for days
     *
     * @param  string $pattern Pattern to use for days
     * @return array<array-key, string|false>
     */
    protected function getDaysOptions(string $pattern): array
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
        for ($day = 1; $day <= 31; $day++) {
            $key = $date->format(format: 'd');
            $value = $valueFormatter->format(datetime: $date);
            $result[$key] = $value;

            $date->modify(modifier: '+1 day');
        }

        return $result;
    }

    /**
     * Create a key => value options for hours
     *
     * @param  string $pattern Pattern to use for hours
     * @return array<array-key, string|false>
     */
    protected function getHoursOptions(string $pattern): array
    {
        $valueFormatter = new IntlDateFormatter(
            locale: $this->getLocale(),
            dateType: IntlDateFormatter::NONE,
            timeType: IntlDateFormatter::NONE,
            timezone: null,
            calendar: null,
            pattern: $pattern,
        );
        $date = new DateTime(datetime: '1970-01-01 00:00:00');

        $result = [];
        for ($hour = 0; $hour <= 23; $hour++) {
            $key = $date->format(format: 'H');
            $value = $valueFormatter->format(datetime: $date);
            $result[$key] = $value;

            $date->modify(modifier: '+1 hour');
        }

        return $result;
    }

    /**
     * Create a key => value options for minutes
     *
     * @param  string $pattern Pattern to use for minutes
     * @return array<array-key, string|false>
     */
    protected function getMinutesOptions(string $pattern): array
    {
        $valueFormatter = new IntlDateFormatter(
            locale: $this->getLocale(),
            dateType: IntlDateFormatter::NONE,
            timeType: IntlDateFormatter::NONE,
            timezone: null,
            calendar: null,
            pattern: $pattern,
        );
        $date = new DateTime(datetime: '1970-01-01 00:00:00');

        $result = [];
        for ($min = 0; $min <= 59; $min++) {
            $key = $date->format(format: 'i');
            $value = $valueFormatter->format(datetime: $date);
            $result[$key] = $value;

            $date->modify(modifier: '+1 minute');
        }

        return $result;
    }

    /**
     * Create a key => value options for seconds
     *
     * @param  string $pattern Pattern to use for seconds
     * @return array<array-key, string|false>
     */
    protected function getSecondsOptions(string $pattern): array
    {
        $valueFormatter = new IntlDateFormatter(
            locale: $this->getLocale(),
            dateType: IntlDateFormatter::NONE,
            timeType: IntlDateFormatter::NONE,
            timezone: null,
            calendar: null,
            pattern: $pattern,
        );
        $date = new DateTime(datetime: '1970-01-01 00:00:00');

        $result = [];
        for ($sec = 0; $sec <= 59; $sec++) {
            $key = $date->format(format: 's');
            $value = $valueFormatter->format(datetime: $date);
            $result[$key] = $value;

            $date->modify(modifier: '+1 second');
        }

        return $result;
    }
}
