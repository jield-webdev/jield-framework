<?php

declare(strict_types=1);

namespace Jield\Form\Element;

use DateInterval;
use Laminas\Validator\DateStep as DateStepValidator;
use Laminas\Validator\Regex as RegexValidator;
use Laminas\Validator\ValidatorInterface;

/** @final */
class Month extends AbstractDateTime
{
    /**
     * A valid format string accepted by date()
     *
     * @var string
     */
    protected string $format = '!Y-m';

    /** @var array<string, scalar|null>  */
    protected array $attributes = [
        'type' => 'month',
    ];

    /**
     * Retrieves a Date Validator configured for a Month Input type
     */
    protected function getDateValidator(): ValidatorInterface
    {
        return new RegexValidator(options: '/^[0-9]{4}\-(0[1-9]|1[012])$/');
    }

    /**
     * Retrieves a DateStep Validator configured for a Month Input type
     */
    protected function getStepValidator(): ValidatorInterface
    {
        $stepValue = $this->attributes['step'] ?? 1; // Months

        $baseValue = $this->attributes['min'] ?? '1970-01';

        return new DateStepValidator(options: [
            'format' => 'Y-m',
            'baseValue' => (string) $baseValue,
            'step' => new DateInterval(duration: "P{$stepValue}M"),
        ]);
    }
}
