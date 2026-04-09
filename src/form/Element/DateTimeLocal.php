<?php

declare(strict_types=1);

namespace Jield\Form\Element;

use DateInterval;
use Laminas\Validator\DateStep as DateStepValidator;
use Laminas\Validator\ValidatorInterface;

/** @final */
class DateTimeLocal extends AbstractDateTime
{
    /** @var array<string, scalar|null>  */
    protected array $attributes = [
        'type' => 'datetime-local',
    ];

    /** @inheritDoc */
    protected string $format = 'Y-m-d\TH:i';

    /**
     * Retrieves a DateStepValidator configured for a Date Input type
     */
    protected function getStepValidator(): ValidatorInterface
    {
        $stepValue = $this->attributes['step'] ?? 1; // Minutes

        $baseValue = $this->attributes['min'] ?? '1970-01-01T00:00';

        return new DateStepValidator(options: [
            'format'    => $this->format,
            'baseValue' => $baseValue,
            'step'      => new DateInterval(duration: "PT{$stepValue}M"),
        ]);
    }
}
