<?php

declare(strict_types=1);

namespace Jield\Form\Element;

use DateInterval;
use Jield\Validator\WeekComparison;
use Laminas\Validator\DateStep as DateStepValidator;
use Laminas\Validator\Regex as RegexValidator;
use Laminas\Validator\ValidatorInterface;

/** @final */
class Week extends AbstractDateTime
{
    /** @var array<string, scalar|null>  */
    protected array $attributes = [
        'type' => 'week',
    ];

    /**
     * Retrieves a Date Validator configured for a Week Input type
     */
    protected function getDateValidator(): ValidatorInterface
    {
        return new RegexValidator(options: '/^[0-9]{4}\-W[0-9]{2}$/');
    }

    /**
     * Retrieves a DateStep Validator configured for a Week Input type
     *
     * @throws \DateMalformedIntervalStringException
     */
    protected function getStepValidator(): ValidatorInterface
    {
        $stepValue = $this->attributes['step'] ?? 1; // Weeks

        $baseValue = $this->attributes['min'] ?? '1970-W01';

        return new DateStepValidator(options: [
            'format' => 'Y-\WW',
            'baseValue' => (string) $baseValue,
            'step' => new DateInterval(duration: "P{$stepValue}W"),
        ]);
    }

    /**
     * @see https://bugs.php.net/bug.php?id=74511
     *
     * @return array<ValidatorInterface>
     * @throws \DateMalformedIntervalStringException
     */
    protected function getValidators(): array
    {
        if ($this->validators) {
            return $this->validators;
        }
        $validators = [];
        $validators[] = $this->getDateValidator();
        if (isset($this->attributes['min']) || isset($this->attributes['max'])) {
            $validators[] = new WeekComparison(
                isset($this->attributes['min']) ? (string) $this->attributes['min'] : null,
                isset($this->attributes['max']) ? (string) $this->attributes['max'] : null,
            );
        }
        if (!isset($this->attributes['step']) || 'any' !== $this->attributes['step']) {
            $validators[] = $this->getStepValidator();
        }
        $this->validators = $validators;
        return $this->validators;
    }
}
