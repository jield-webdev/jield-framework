<?php

declare(strict_types=1);

namespace Jield\Form\Element;

use DateTime as PhpDateTime;
use DateTimeInterface;
use Exception;
use Jield\Form\Exception\InvalidArgumentException;
use Jield\Form\FormInterface;
use Laminas\Validator\Date as DateValidator;
use Laminas\Validator\ValidatorInterface;

use function array_merge;
use function is_array;
use function is_string;
use function sprintf;

class DateSelect extends MonthSelect
{
    /**
     * Select form element that contains values for day
     *
     * @var Select
     */
    protected Select $dayElement;

    /**
     * Constructor. Add the day select element
     *
     * @param  null|int|string $name    Optional name for the element
     * @param  iterable        $options Optional options for the element
     */
    public function __construct($name = null, iterable $options = [])
    {
        $this->dayElement = new Select(name: 'day');

        parent::__construct(name: $name, options: $options);
    }

    /**
     * Accepted options for DateSelect (plus the ones from MonthSelect) :
     * - day_attributes: HTML attributes to be rendered with the day element
     *
     * @inheritDoc
     */
    public function setOptions(iterable $options): static
    {
        parent::setOptions(options: $options);

        if (isset($this->options['day_attributes'])) {
            $this->setDayAttributes(dayAttributes: \Jield\Stdlib\Value::scalarMap($this->options['day_attributes']));
        }

        return $this;
    }

    public function getDayElement(): Select
    {
        return $this->dayElement;
    }

    /**
     * Get both the year and month elements
     *
     * @return list<Select>
     */
    public function getElements(): array
    {
        return array_merge([$this->dayElement], parent::getElements());
    }

    /**
     * Set the day attributes
     *
     * @param array<string, scalar|null> $dayAttributes
     * @return static
     */
    public function setDayAttributes(array $dayAttributes): static
    {
        $this->dayElement->setAttributes(arrayOrTraversable: $dayAttributes);
        return $this;
    }

    /**
     * Get the day attributes
     *
     * @return array<string, scalar|null>
     */
    public function getDayAttributes(): array
    {
        return $this->dayElement->getAttributes();
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
                    message: 'Value should be a parsable string or an instance of DateTime',
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
            ];
        }

        if (is_array(value: $value)) {
            $this->yearElement->setValue(value: $value['year']);
            $this->monthElement->setValue(value: $value['month']);
            $this->dayElement->setValue(value: $value['day']);
        } else {
            $this->yearElement->setValue(value: null);
            $this->monthElement->setValue(value: null);
            $this->dayElement->setValue(value: null);
        }

        return $this;
    }

    public function getValue(): ?string
    {
        /** @var mixed $year */
        $year = $this->getYearElement()->getValue();
        /** @var mixed $month */
        $month = $this->getMonthElement()->getValue();
        /** @var mixed $day */
        $day = $this->getDayElement()->getValue();

        if ($this->shouldCreateEmptyOption() && null === $year && null === $month && null === $day) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', \Jield\Stdlib\Value::scalar($year), \Jield\Stdlib\Value::scalar($month), \Jield\Stdlib\Value::scalar($day));
    }

    /**
     * Prepare the form element (mostly used for rendering purposes)
     */
    public function prepareElement(FormInterface $form): void
    {
        parent::prepareElement(form: $form);

        $name = $this->getName();
        $this->dayElement->setName(name: (string) ($name) . '[day]');
    }

    /**
     * Get validator
     */
    protected function getValidator(): ValidatorInterface
    {
        if (null === $this->validator) {
            $this->validator = new DateValidator(options: ['format' => 'Y-m-d']);
        }

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
                ['name' => 'DateSelect'],
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
    }
}
