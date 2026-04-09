<?php

declare(strict_types=1);

namespace Jield\Form\Element;

use Jield\Form\Element\Number as NumberElement;
use Jield\Validator\IsFloat as NumberValidator;
use Laminas\Validator\NumberComparison;
use Laminas\Validator\Step as StepValidator;
use Laminas\Validator\ValidatorInterface;

/** @final */
class Range extends NumberElement
{
    /** @var array<string, scalar|null> */
    protected array $attributes
        = [
            'type' => 'range',
        ];

    /**
     * Get validator
     *
     * @return ValidatorInterface[]
     */
    protected function getValidators(): array
    {
        if ($this->validators) {
            return $this->validators;
        }

        $validators   = [];
        $validators[] = new NumberValidator();

        $inclusive = true;
        if (!empty($this->attributes['inclusive'])) {
            $inclusive = $this->attributes['inclusive'];
        }

        $validators[] = new NumberComparison([
            'min'          => $this->attributes['min'] ?? 0,
            'max'          => $this->attributes['max'] ?? 100,
            'inclusiveMin' => $inclusive,
            'inclusiveMax' => $inclusive,
        ]);

        if (
            !isset($this->attributes['step'])
            || 'any' !== $this->attributes['step']
        ) {
            $validators[] = new StepValidator(options: [
                'baseValue' => $this->attributes['min'] ?? 0,
                'step'      => $this->attributes['step'] ?? 1,
            ]);
        }

        $this->validators = $validators;
        return $this->validators;
    }
}
