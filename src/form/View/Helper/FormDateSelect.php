<?php

declare(strict_types=1);

namespace Jield\Form\View\Helper;

use DateTime;
use IntlDateFormatter;
use Jield\Form\Element\DateSelect as DateSelectElement;
use Jield\Form\ElementInterface;
use Jield\Form\Exception;

use function is_numeric;
use function sprintf;

/** @final */
class FormDateSelect extends AbstractFormDateSelect
{
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
        ?string $locale = null,
    ): string|static {
        if (!$element) {
            return $this;
        }

        $this->setDateType(dateType: $dateType);

        if ($locale !== null) {
            $this->setLocale(locale: $locale);
        }

        return $this->render(element: $element);
    }

    /**
     * Render a date element that is composed of three selects
     *
     * @throws Exception\InvalidArgumentException
     * @throws Exception\DomainException
     */
    public function render(ElementInterface $element): string
    {
        if (!$element instanceof DateSelectElement) {
            throw new Exception\InvalidArgumentException(message: sprintf(
                '%s requires that the element is of type Jield\Form\Element\DateSelect',
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

        $selectHelper = $this->getSelectElementHelper();
        $pattern = $this->parsePattern(renderDelimiters: $element->shouldRenderDelimiters());

        $daysOptions = $this->getDaysOptions(pattern: \Jield\Stdlib\Value::string($pattern['day']));
        $monthsOptions = $this->getMonthsOptions(pattern: \Jield\Stdlib\Value::string($pattern['month']));
        $yearOptions = $this->getYearsOptions(minYear: $element->getMinYear(), maxYear: $element->getMaxYear());

        $dayElement = $element->getDayElement()->setValueOptions(options: $daysOptions);
        $monthElement = $element->getMonthElement()->setValueOptions(options: $monthsOptions);
        $yearElement = $element->getYearElement()->setValueOptions(options: $yearOptions);

        if ($element->shouldCreateEmptyOption()) {
            $dayElement->setEmptyOption(emptyOption: '');
            $yearElement->setEmptyOption(emptyOption: '');
            $monthElement->setEmptyOption(emptyOption: '');
        }

        $data = [];
        $data[$pattern['day']] = $selectHelper->render(element: $dayElement);
        $data[$pattern['month']] = $selectHelper->render(element: $monthElement);
        $data[\Jield\Stdlib\Value::key($pattern['year'])] = $selectHelper->render(element: $yearElement);

        $markup = '';
        /** @var mixed $value */
        foreach ($pattern as $key => $value) {
            // Delimiter
            if (is_numeric(value: $key)) {
                $markup .= \Jield\Stdlib\Value::stringify($value);
            } else {
                $markup .= $data[$value];
            }
        }

        return $markup;
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
}
