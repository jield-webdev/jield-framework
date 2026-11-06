<?php

declare(strict_types=1);

namespace Jield\Form\Element;

use Jield\Form\Element\Number as NumberElement;
use Laminas\Validator\NumberComparison;
use Laminas\Validator\Step as StepValidator;
use Laminas\Validator\ValidatorInterface;

/** @final */
class Range extends NumberElement
{
    /** @var array<string, scalar|null> */
    protected array $attributes = [
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

        $validators = [];

        $inclusive = true;
        if (!empty($this->attributes['inclusive'])) {
            $inclusive = $this->attributes['inclusive'];
        }

        $validators[] = new NumberComparison([
            'min' => \Jield\Stdlib\Value::numeric($this->attributes['min'] ?? 0),
            'max' => \Jield\Stdlib\Value::numeric($this->attributes['max'] ?? 100),
            'inclusiveMin' => (bool) $inclusive,
            'inclusiveMax' => (bool) $inclusive,
        ]);

        if (!isset($this->attributes['step']) || 'any' !== $this->attributes['step']) {
            $validators[] = new StepValidator(options: [
                'baseValue' => \Jield\Stdlib\Value::numeric($this->attributes['min'] ?? 0),
                'step' => \Jield\Stdlib\Value::numeric($this->attributes['step'] ?? 1),
            ]);
        }

        $this->validators = $validators;
        return $this->validators;
    }
}
