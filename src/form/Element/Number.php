<?php

declare(strict_types=1);

namespace Jield\Form\Element;

use Jield\Form\Element;
use Jield\Stdlib\Value;
use Laminas\Filter\StringTrim;
use Laminas\InputFilter\InputProviderInterface;
use Laminas\Validator\NumberComparison;
use Laminas\Validator\Regex as RegexValidator;
use Laminas\Validator\Step as StepValidator;
use Laminas\Validator\ValidatorInterface;

class Number extends Element implements InputProviderInterface
{
    /** @var array<string, scalar|null>  */
    protected array $attributes = [
        'type' => 'number',
    ];

    /** @var array<array-key, ValidatorInterface> */
    protected array $validators = [];

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
        // HTML5 always transmits values in the format "1000.01", without a
        // thousand separator. The prior use of the i18n Float validator
        // allowed the thousand separator, which resulted in wrong numbers
        // when casting to float.
        $validators[] = new RegexValidator(options: '(^-?\d*(\.\d+)?$)');

        $inclusive = true;
        if (isset($this->attributes['inclusive'])) {
            $inclusive = $this->attributes['inclusive'];
        }

        if (isset($this->attributes['min'])) {
            $validators[] = new NumberComparison([
                'min' => Value::numeric($this->attributes['min']),
                'inclusiveMin' => (bool) $inclusive,
                'inclusiveMax' => (bool) $inclusive,
            ]);
        }
        if (isset($this->attributes['max'])) {
            $validators[] = new NumberComparison([
                'max' => Value::numeric($this->attributes['max']),
                'inclusiveMin' => (bool) $inclusive,
                'inclusiveMax' => (bool) $inclusive,
            ]);
        }

        if (!isset($this->attributes['step']) || 'any' !== $this->attributes['step']) {
            $validators[] = new StepValidator(options: [
                'baseValue' => Value::numeric($this->attributes['min'] ?? 0),
                'step' => Value::numeric($this->attributes['step'] ?? 1),
            ]);
        }

        $this->validators = $validators;
        return $this->validators;
    }

    /**
     * Provide default input rules for this element
     *
     * Attaches a number validator, as well as a greater than and less than validators
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
}
