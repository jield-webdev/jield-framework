<?php

declare(strict_types=1);

namespace Jield\Form\Element;

use DateInterval;
use DateTime as PhpDateTime;
use DateTimeInterface;
use Jield\Form\Element;
use Jield\Form\Exception\InvalidArgumentException;
use Jield\Stdlib\Value;
use Laminas\Filter\StringTrim;
use Laminas\InputFilter\InputProviderInterface;
use Laminas\Validator\Date as DateValidator;
use Laminas\Validator\DateComparison;
use Laminas\Validator\DateStep as DateStepValidator;
use Laminas\Validator\ValidatorInterface;

use function date;
use function sprintf;

abstract class AbstractDateTime extends Element implements InputProviderInterface
{
    /**
     * A valid format string accepted by date()
     *
     * @var string
     */
    protected string $format = 'Y-m-d\TH:iP';

    /** @var array<ValidatorInterface> */
    protected array $validators = [];

    /**
     * Accepted options for DateTime:
     * - format: A \DateTime compatible string
     *
     * @inheritDoc
     */
    public function setOptions(iterable $options): static
    {
        parent::setOptions(options: $options);

        if (isset($this->options['format'])) {
            $this->setFormat(format: Value::string($this->options['format']));
        }

        return $this;
    }

    /**
     * Retrieve the element value
     *
     * If the value is instance of DateTimeInterface, and $returnFormattedValue
     * is true (the default), we return the string representation using the
     * currently registered format.
     *
     * If $returnFormattedValue is false, the original value will be
     * returned, regardless of type.
     *
     * @inheritDoc
     */
    public function getValue(bool $returnFormattedValue = true): mixed
    {
        $value = parent::getValue();
        if (!$value instanceof DateTimeInterface || !$returnFormattedValue) {
            return $value;
        }
        $format = $this->getFormat();
        return $value->format(format: $format);
    }

    /**
     * Set value for format
     *
     * @return static
     */
    public function setFormat(string $format): static
    {
        $this->format = $format;
        return $this;
    }

    /**
     * Retrieve the DateTime format to use for the value
     */
    public function getFormat(): string
    {
        return $this->format;
    }

    /**
     * Get validators
     *
     * @return array<ValidatorInterface>
     */
    protected function getValidators(): array
    {
        if ($this->validators) {
            return $this->validators;
        }

        $validators = [];
        $validators[] = $this->getDateValidator();

        if (
            isset($this->attributes['min'])
            && $this->valueIsValidDateTimeFormat(value: (string) $this->attributes['min'])
        ) {
            $validators[] = new DateComparison([
                'min' => (string) $this->attributes['min'],
                'inclusiveMin' => true,
                'inclusiveMax' => true,
                'inputFormat' => $this->format,
            ]);
        } elseif (
            isset($this->attributes['min'])
            && !$this->valueIsValidDateTimeFormat(value: (string) $this->attributes['min'])
        ) {
            throw new InvalidArgumentException(message: sprintf(
                '%1$s expects "min" to conform to %2$s; received "%3$s"',
                __METHOD__,
                $this->format,
                (string) $this->attributes['min'],
            ));
        }

        if (
            isset($this->attributes['max'])
            && $this->valueIsValidDateTimeFormat(value: (string) $this->attributes['max'])
        ) {
            $validators[] = new DateComparison([
                'max' => (string) $this->attributes['max'],
                'inclusiveMin' => true,
                'inclusiveMax' => true,
                'inputFormat' => $this->format,
            ]);
        } elseif (
            isset($this->attributes['max'])
            && !$this->valueIsValidDateTimeFormat(value: (string) $this->attributes['max'])
        ) {
            throw new InvalidArgumentException(message: sprintf(
                '%1$s expects "max" to conform to %2$s; received "%3$s"',
                __METHOD__,
                $this->format,
                (string) $this->attributes['max'],
            ));
        }
        if (!isset($this->attributes['step']) || 'any' !== $this->attributes['step']) {
            $validators[] = $this->getStepValidator();
        }

        $this->validators = $validators;
        return $this->validators;
    }

    /**
     * Retrieves a Date Validator configured for a DateTime Input type
     */
    protected function getDateValidator(): ValidatorInterface
    {
        return new DateValidator(options: ['format' => $this->format]);
    }

    /**
     * Retrieves a DateStep Validator configured for a DateTime Input type
     */
    protected function getStepValidator(): ValidatorInterface
    {
        $format = $this->getFormat();
        $stepValue = $this->attributes['step'] ?? 1; // Minutes

        $baseValue = $this->attributes['min'] ?? date(format: $format, timestamp: 0);

        return new DateStepValidator(options: [
            'format' => $format,
            'baseValue' => (string) $baseValue,
            'step' => new DateInterval(duration: "PT{$stepValue}M"),
        ]);
    }

    /**
     * Provide default input rules for this element
     *
     * Attaches default validators for the datetime input.
     *
     * @inheritDoc
     */
    public function getInputSpecification(): array
    {
        $spec = [
            'required' => true,
            'filters' => [
                ['name' => StringTrim::class],
            ],
            'validators' => $this->getValidators(),
        ];

        $name = $this->getName();
        if ($name !== null) {
            $spec['name'] = $name;
        }

        return $spec;
    }

    /**
     * Indicate whether or not a value represents a valid DateTime format.
     */
    private function valueIsValidDateTimeFormat(string $value): bool
    {
        return PhpDateTime::createFromFormat(format: $this->format, datetime: $value) instanceof DateTimeInterface;
    }
}
