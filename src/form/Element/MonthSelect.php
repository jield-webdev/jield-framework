<?php

declare(strict_types=1);

namespace Jield\Form\Element;

use DateTime as PhpDateTime;
use DateTimeInterface;
use Exception;
use Jield\Form\Element;
use Jield\Form\ElementPrepareAwareInterface;
use Jield\Form\Exception\InvalidArgumentException;
use Jield\Form\FormInterface;
use Laminas\InputFilter\InputProviderInterface;
use Laminas\Validator\Regex as RegexValidator;
use Laminas\Validator\ValidatorInterface;

use function date;
use function is_array;
use function is_string;
use function sprintf;

// Preserve the existing public API and the state and operations that implement it.
// @mago-expect lint:cyclomatic-complexity(1)
// @mago-expect lint:too-many-methods(1)
class MonthSelect extends Element implements InputProviderInterface, ElementPrepareAwareInterface
{
    /**
     * Select form element that contains values for month
     */
    protected Select $monthElement;

    /**
     * Select form element that contains values for year
     */
    protected Select $yearElement;

    /**
     * Min year to use for the select (default: current year - 100)
     */
    protected int $minYear;

    /**
     * Max year to use for the select (default: current year)
     */
    protected int $maxYear;

    /**
     * If set to true, it will generate an empty option for every select (this is mainly needed by most JavaScript
     * libraries to allow to have a placeholder)
     */
    protected bool $createEmptyOption = false;

    /**
     * If set to true, view helpers will render delimiters between <select> elements, according to the
     * specified locale
     */
    protected bool $renderDelimiters = true;

    protected ?ValidatorInterface $validator = null;

    /**
     * Constructor. Add two selects elements
     *
     * @param int|string|null $name    Optional name for the element
     * @param  iterable        $options Optional options for the element
     */
    public function __construct(int|string|null $name = null, iterable $options = [])
    {
        $this->minYear = (int) date(format: 'Y') - 100;
        $this->maxYear = (int) date(format: 'Y');

        $this->monthElement = new Select(name: 'month');
        $this->yearElement = new Select(name: 'year');

        parent::__construct(name: $name, options: $options);
    }

    /**
     * Set element options.
     *
     * Accepted options for MonthSelect:
     *
     * - month_attributes: HTML attributes to be rendered with the month element
     * - year_attributes: HTML attributes to be rendered with the month element
     * - min_year: min year to use in the year select
     * - max_year: max year to use in the year select
     *
     * @inheritDoc
     */
    public function setOptions(iterable $options): static
    {
        parent::setOptions(options: $options);

        if (($this->options['month_attributes'] ?? null) !== null) {
            $this->setMonthAttributes(monthAttributes: \Jield\Stdlib\Value::scalarMap(
                $this->options['month_attributes'],
            ));
        }

        if (($this->options['year_attributes'] ?? null) !== null) {
            $this->setYearAttributes(yearAttributes: \Jield\Stdlib\Value::scalarMap($this->options['year_attributes']));
        }

        if (($this->options['min_year'] ?? null) !== null) {
            $this->setMinYear(minYear: \Webmozart\Assert\Assert::integer($this->options['min_year']));
        }

        if (($this->options['max_year'] ?? null) !== null) {
            $this->setMaxYear(maxYear: \Webmozart\Assert\Assert::integer($this->options['max_year']));
        }

        if (($this->options['create_empty_option'] ?? null) !== null) {
            $this->setShouldCreateEmptyOption(createEmptyOption: \Webmozart\Assert\Assert::boolean(
                $this->options['create_empty_option'],
            ));
        }

        if (($this->options['render_delimiters'] ?? null) !== null) {
            $this->setShouldRenderDelimiters(renderDelimiters: \Webmozart\Assert\Assert::boolean(
                $this->options['render_delimiters'],
            ));
        }

        return $this;
    }

    public function getMonthElement(): Select
    {
        return $this->monthElement;
    }

    public function getYearElement(): Select
    {
        return $this->yearElement;
    }

    /**
     * Get both the year and month elements
     *
     * @return list<Select>
     */
    public function getElements(): array
    {
        return [$this->monthElement, $this->yearElement];
    }

    /**
     * Set the month attributes
     *
     * @param array<string, scalar|null> $monthAttributes
     */
    public function setMonthAttributes(array $monthAttributes): static
    {
        $this->monthElement->setAttributes(arrayOrTraversable: $monthAttributes);
        return $this;
    }

    /**
     * Get the month attributes
     *
     * @return array<string, scalar|null>
     */
    public function getMonthAttributes(): array
    {
        return $this->monthElement->getAttributes();
    }

    /**
     * Set the year attributes
     *
     * @param array<string, scalar|null> $yearAttributes
     */
    public function setYearAttributes(array $yearAttributes): static
    {
        $this->yearElement->setAttributes(arrayOrTraversable: $yearAttributes);
        return $this;
    }

    /**
     * Get the year attributes
     *
     * @return array<string, scalar|null>
     */
    public function getYearAttributes(): array
    {
        return $this->yearElement->getAttributes();
    }

    public function setMinYear(int $minYear): static
    {
        $this->minYear = $minYear;
        return $this;
    }

    public function getMinYear(): int
    {
        return $this->minYear;
    }

    public function setMaxYear(int $maxYear): static
    {
        $this->maxYear = $maxYear;
        return $this;
    }

    public function getMaxYear(): int
    {
        return $this->maxYear;
    }

    public function setShouldCreateEmptyOption(bool $createEmptyOption): static
    {
        $this->createEmptyOption = $createEmptyOption;
        return $this;
    }

    public function shouldCreateEmptyOption(): bool
    {
        return $this->createEmptyOption;
    }

    public function setShouldRenderDelimiters(bool $renderDelimiters): static
    {
        $this->renderDelimiters = $renderDelimiters;
        return $this;
    }

    public function shouldRenderDelimiters(): bool
    {
        return $this->renderDelimiters;
    }

    /**
     * @inheritDoc
     * @param mixed $value
     * @throws InvalidArgumentException
     */
    public function setValue($value): static
    {
        if (is_string(value: $value)) {
            try {
                $value = new PhpDateTime(datetime: $value);
            } catch (Exception $exception) {
                throw new InvalidArgumentException(
                    message: 'Value should be a parsable string or an instance of \DateTime',
                    code: 0,
                    previous: $exception,
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
            ];
        }
        if (is_array(value: $value)) {
            $this->yearElement->setValue(value: $value['year']);
            $this->monthElement->setValue(value: $value['month']);
            return $this;
        }
        $this->yearElement->setValue(value: null);
        $this->monthElement->setValue(value: null);

        return $this;
    }

    public function getValue(): ?string
    {
        /** @mago-var mixed $year */
        $year = $this->getYearElement()->getValue();
        /** @mago-var mixed $month */
        $month = $this->getMonthElement()->getValue();

        if ($this->shouldCreateEmptyOption() && null === $year && null === $month) {
            return null;
        }

        return sprintf('%04d-%02d', \Jield\Stdlib\Value::scalar($year), \Jield\Stdlib\Value::scalar($month));
    }

    /**
     * Prepare the form element (mostly used for rendering purposes)
     */
    public function prepareElement(FormInterface $form): void
    {
        $name = $this->getName();
        $this->monthElement->setName(name: (string) $name . '[month]');
        $this->yearElement->setName(name: (string) $name . '[year]');
    }

    /**
     * Get validator
     */
    protected function getValidator(): ValidatorInterface
    {
        return new RegexValidator(options: '/^[0-9]{4}\-(0?[1-9]|1[012])$/');
    }

    /**
     * @inheritDoc
     */
    public function getInputSpecification(): array
    {
        $spec = [
            'required' => false,
            'filters' => [
                ['name' => 'MonthSelect'],
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
        $this->monthElement = clone $this->monthElement;
        $this->yearElement = clone $this->yearElement;
    }
}
