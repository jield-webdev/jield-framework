<?php

declare(strict_types=1);

namespace Jield\Form\Element;

use DateInterval;
use Laminas\Validator\DateStep as DateStepValidator;
use Laminas\Validator\ValidatorInterface;

use function date;

/** @final */
class Time extends AbstractDateTime
{
    /** @var array<string, scalar|null>  */
    protected array $attributes = [
        'type' => 'time',
    ];

    /**
     * Default date format
     */
    protected string $format = 'H:i:s';

    /**
     * Retrieves a DateStepValidator configured for a Date Input type
     *
     * @throws \DateMalformedIntervalStringException
     */
    protected function getStepValidator(): ValidatorInterface
    {
        $format = $this->getFormat();
        $stepValue = $this->attributes['step'] ?? 60; // Seconds

        $baseValue = $this->attributes['min'] ?? date(format: $format, timestamp: 0);

        return new DateStepValidator(options: [
            'format' => $format,
            'baseValue' => (string) $baseValue,
            'step' => new DateInterval(duration: "PT{$stepValue}S"),
        ]);
    }
}
