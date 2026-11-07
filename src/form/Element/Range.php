<?php

declare(strict_types=1);

namespace Jield\Form\Element;

use Jield\Form\Element\Number as NumberElement;
use Laminas\Validator\NumberComparison;
use Laminas\Validator\Step as StepValidator;
use Laminas\Validator\ValidatorInterface;
use Webmozart\Assert\Assert;

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
            'min' => Assert::numeric($this->attributes['min'] ?? 0),
            'max' => Assert::numeric($this->attributes['max'] ?? 100),
            'inclusiveMin' => (bool) $inclusive,
            'inclusiveMax' => (bool) $inclusive,
        ]);

        if (!isset($this->attributes['step']) || 'any' !== $this->attributes['step']) {
            $validators[] = new StepValidator(options: [
                'baseValue' => Assert::numeric($this->attributes['min'] ?? 0),
                'step' => Assert::numeric($this->attributes['step'] ?? 1),
            ]);
        }

        $this->validators = $validators;
        return $this->validators;
    }
}
