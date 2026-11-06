<?php

declare(strict_types=1);

namespace Jield\Form\View\Helper;

use IntlDateFormatter;
use Jield\Form\Element\MonthSelect as MonthSelectElement;
use Jield\Form\ElementInterface;
use Jield\Form\Exception;

use function is_numeric;
use function sprintf;

/** @final */
class FormMonthSelect extends AbstractFormDateSelect
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
     * Render a month element that is composed of two selects
     *
     * @throws Exception\InvalidArgumentException
     * @throws Exception\DomainException
     */
    public function render(ElementInterface $element): string
    {
        if (!$element instanceof MonthSelectElement) {
            throw new Exception\InvalidArgumentException(message: sprintf(
                '%s requires that the element is of type Jield\Form\Element\MonthSelect',
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

        // The pattern always contains "day" part and the first separator, so we have to remove it
        unset($pattern['day']);
        unset($pattern[0]);

        $monthsOptions = $this->getMonthsOptions(pattern: \Jield\Stdlib\Value::string($pattern['month']));
        $yearOptions = $this->getYearsOptions(minYear: $element->getMinYear(), maxYear: $element->getMaxYear());

        $monthElement = $element->getMonthElement()->setValueOptions(options: $monthsOptions);
        $yearElement = $element->getYearElement()->setValueOptions(options: $yearOptions);

        if ($element->shouldCreateEmptyOption()) {
            $monthElement->setEmptyOption(emptyOption: '');
            $yearElement->setEmptyOption(emptyOption: '');
        }

        $data = [];
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
}
