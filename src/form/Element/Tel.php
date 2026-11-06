<?php

declare(strict_types=1);

namespace Jield\Form\Element;

use Jield\Form\Element;
use Laminas\Filter\StringTrim;
use Laminas\Filter\StripNewlines;
use Laminas\InputFilter\InputProviderInterface;
use Laminas\Validator\Regex as RegexValidator;
use Laminas\Validator\ValidatorInterface;

/** @final */
class Tel extends Element implements InputProviderInterface
{
    /** @var array<string, scalar|null>  */
    protected array $attributes = [
        'type' => 'tel',
    ];

    /** @var null|ValidatorInterface */
    protected ?ValidatorInterface $validator = null;

    /**
     * Get validator
     */
    protected function getValidator(): ValidatorInterface
    {
        if (null === $this->validator) {
            $this->validator = new RegexValidator(options: "/^[^\r\n]*$/");
        }
        return $this->validator;
    }

    /**
     * Provide default input rules for this element
     *
     * @inheritDoc
     */
    public function getInputSpecification(): array
    {
        $spec = [
            'required' => true,
            'filters' => [
                ['name' => StringTrim::class],
                ['name' => StripNewlines::class],
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
}
