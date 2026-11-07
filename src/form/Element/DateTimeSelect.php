<?php

declare(strict_types=1);

namespace Jield\Form\Element;

use DateTime as PhpDateTime;
use DateTimeInterface;
use Exception;
use Jield\Form\Exception\InvalidArgumentException;
use Jield\Form\FormInterface;
use Jield\Stdlib\Value;
use Laminas\Validator\Date as DateValidator;
use Laminas\Validator\ValidatorInterface;

use function array_merge;
use function is_array;
use function is_string;
use function sprintf;

/** @final */
class DateTimeSelect extends DateSelect
{
    /**
     * Select form element that contains values for hour
     *
     * @var Select
     */
    protected Select $hourElement;

    /**
     * Select form element that contains values for minute
     *
     * @var Select
     */
    protected Select $minuteElement;

    /**
     * Select form element that contains values for second
     *
     * @var Select
     */
    protected Select $secondElement;

    /**
     * Is the seconds select shown when the element is rendered?
     *
     * @var bool
     */
    protected bool $shouldShowSeconds = false;

    /**
     * Constructor. Add the hour, minute and second select elements
     *
     * @param  null|int|string  $name    Optional name for the element
     * @param  array            $options Optional options for the element
     */
    public function __construct($name = null, array $options = [])
    {
        parent::__construct(name: $name, options: $options);

        $this->hourElement = new Select(name: 'hour');
        $this->minuteElement = new Select(name: 'minute');
        $this->secondElement = new Select(name: 'second');
    }

    /**
     * Set options for DateTimeSelect element.
     *
     * Accepted options for DateTimeSelect (plus the ones from DateSelect):
     *
     * - hour_attributes: HTML attributes to be rendered with the hour element
     * - minute_attributes: HTML attributes to be rendered with the minute element
     * - second_attributes: HTML attributes to be rendered with the second element
     * - should_show_seconds: if set to true, the seconds select is shown
     *
     * @inheritDoc
     */
    public function setOptions(iterable $options): static
    {
        parent::setOptions(options: $options);

        if (isset($this->options['hour_attributes'])) {
            $this->setHourAttributes(hourAttributes: Value::scalarMap($this->options['hour_attributes']));
        }

        if (isset($this->options['minute_attributes'])) {
            $this->setMinuteAttributes(minuteAttributes: Value::scalarMap(
                $this->options['minute_attributes'],
            ));
        }

        if (isset($this->options['second_attributes'])) {
            $this->setSecondAttributes(secondAttributes: Value::scalarMap(
                $this->options['second_attributes'],
            ));
        }

        if (isset($this->options['should_show_seconds'])) {
            $this->setShouldShowSeconds(shouldShowSeconds: Value::bool(
                $this->options['should_show_seconds'],
            ));
        }

        return $this;
    }

    public function getHourElement(): Select
    {
        return $this->hourElement;
    }

    public function getMinuteElement(): Select
    {
        return $this->minuteElement;
    }

    public function getSecondElement(): Select
    {
        return $this->secondElement;
    }

    /** @return list<Select> */
    public function getElements(): array
    {
        return array_merge(parent::getElements(), [
            $this->hourElement,
            $this->minuteElement,
            $this->secondElement,
        ]);
    }

    /**
     * Set the hour attributes
     *
     * @param array<string, scalar|null> $hourAttributes
     * @return static
     */
    public function setHourAttributes(array $hourAttributes): static
    {
        $this->hourElement->setAttributes(arrayOrTraversable: $hourAttributes);
        return $this;
    }

    /**
     * Get the hour attributes
     *
     * @return array<string, scalar|null>
     */
    public function getHourAttributes(): array
    {
        return $this->hourElement->getAttributes();
    }

    /**
     * Set the minute attributes
     *
     * @param array<string, scalar|null> $minuteAttributes
     * @return static
     */
    public function setMinuteAttributes(array $minuteAttributes): static
    {
        $this->minuteElement->setAttributes(arrayOrTraversable: $minuteAttributes);
        return $this;
    }

    /**
     * Get the minute attributes
     *
     * @return array<string, scalar|null>
     */
    public function getMinuteAttributes(): array
    {
        return $this->minuteElement->getAttributes();
    }

    /**
     * Set the second attributes
     *
     * @param array<string, scalar|null> $secondAttributes
     * @return static
     */
    public function setSecondAttributes(array $secondAttributes): static
    {
        $this->secondElement->setAttributes(arrayOrTraversable: $secondAttributes);
        return $this;
    }

    /**
     * Get the second attributes
     *
     * @return array<string, scalar|null>
     */
    public function getSecondAttributes(): array
    {
        return $this->secondElement->getAttributes();
    }

    /**
     * If set to true, this indicate that the second select is shown. If set to true, the seconds will be
     * assumed to always be 00
     *
     * @return static
     */
    public function setShouldShowSeconds(bool $shouldShowSeconds): static
    {
        $this->shouldShowSeconds = $shouldShowSeconds;
        return $this;
    }

    public function shouldShowSeconds(): bool
    {
        return $this->shouldShowSeconds;
    }

    /**
     * @param  mixed $value
     * @return static
     * @throws InvalidArgumentException
     */
    public function setValue($value): static
    {
        if (is_string(value: $value)) {
            try {
                $value = new PhpDateTime(datetime: $value);
            } catch (Exception) {
                throw new InvalidArgumentException(
                    message: 'Value should be a parsable string or an instance of \DateTime',
                );
            }
        }

        if (null === $value && !$this->shouldCreateEmptyOption()) {
            $value = new PhpDateTime();
        }

        if ($value instanceof DateTimeInterface) {
            $value = [
                'year' => $value->format(format: 'Y'),
                'month' => $value->format(format: 'm'),
                'day' => $value->format(format: 'd'),
                'hour' => $value->format(format: 'H'),
                'minute' => $value->format(format: 'i'),
                'second' => $value->format(format: 's'),
            ];
        }

        if (is_array(value: $value)) {
            $this->yearElement->setValue(value: $value['year']);
            $this->monthElement->setValue(value: $value['month']);
            $this->dayElement->setValue(value: $value['day']);
            $this->hourElement->setValue(value: $value['hour']);
            $this->minuteElement->setValue(value: $value['minute']);
            $this->secondElement->setValue(value: $value['second'] ?? '00');
        } else {
            $this->yearElement->setValue(value: null);
            $this->monthElement->setValue(value: null);
            $this->dayElement->setValue(value: null);
            $this->hourElement->setValue(value: null);
            $this->minuteElement->setValue(value: null);
            $this->secondElement->setValue(value: null);
        }

        return $this;
    }

    public function getValue(): ?string
    {
        $year = $this->getYearElement()->getValue();
        $month = $this->getMonthElement()->getValue();
        $day = $this->getDayElement()->getValue();
        $hour = $this->getHourElement()->getValue();
        $minute = $this->getMinuteElement()->getValue();
        $second = $this->getSecondElement()->getValue();

        // if everything is null, return null
        if (
            $this->shouldCreateEmptyOption()
            && null === $year
            && null === $month
            && null === $day
            && null === $hour
            && null === $minute
            && (null === $second || '00' === $second)
        ) {
            return null;
        }

        // if time is given, but date is null, use current date
        if ($this->shouldCreateEmptyOption() && null === $year && null === $month && null === $day) {
            $now = new PhpDateTime();
            $year = $now->format(format: 'Y');
            $month = $now->format(format: 'm');
            $day = $now->format(format: 'd');
        }

        // if date is given, but time is null, use 00:00:00 instead
        if (
            $this->shouldCreateEmptyOption()
            && null === $hour
            && null === $minute
            && (null === $second || '00' === $second)
        ) {
            $hour = '00';
            $minute = '00';
            $second = '00';
        }

        return sprintf(
            '%04d-%02d-%02d %02d:%02d:%02d',
            Value::scalar($year),
            Value::scalar($month),
            Value::scalar($day),
            Value::scalar($hour),
            Value::scalar($minute),
            Value::scalar($second),
        );
    }

    /**
     * Prepare the form element (mostly used for rendering purposes)
     */
    public function prepareElement(FormInterface $form): void
    {
        parent::prepareElement(form: $form);

        $name = $this->getName();
        $this->hourElement->setName(name: (string) $name . '[hour]');
        $this->minuteElement->setName(name: (string) $name . '[minute]');
        $this->secondElement->setName(name: (string) $name . '[second]');
    }

    /**
     * Get validator
     */
    protected function getValidator(): ValidatorInterface
    {
        $this->validator ??= new DateValidator(options: ['format' => 'Y-m-d H:i:s']);

        return $this->validator;
    }

    /**
     * @inheritDoc
     */
    public function getInputSpecification(): array
    {
        $spec = [
            'required' => false,
            'filters' => [
                ['name' => 'DateTimeSelect'],
            ],
            'validators' => [
                $this->getValidator(),
            ],
        ];

        $name = $this->getName();
        if ($name !== null) {
            $spec['name'] = $name;
        }

        return $spec;
    }

    /**
     * Clone the element (this is needed by Collection element, as it needs different copies of the elements)
     */
    public function __clone()
    {
        $this->dayElement = clone $this->dayElement;
        $this->monthElement = clone $this->monthElement;
        $this->yearElement = clone $this->yearElement;
        $this->hourElement = clone $this->hourElement;
        $this->minuteElement = clone $this->minuteElement;
        $this->secondElement = clone $this->secondElement;
    }
}
