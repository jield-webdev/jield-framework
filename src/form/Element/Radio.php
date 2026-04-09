<?php

declare(strict_types=1);

namespace Jield\Form\Element;

use Laminas\Validator\InArray as InArrayValidator;
use Laminas\Validator\ValidatorInterface;

/** @final */
class Radio extends MultiCheckbox
{
    /** @var array<string, scalar|null>  */
    protected array $attributes = [
        'type' => 'radio',
    ];

    /**
     * Get validator
     */
    protected function getValidator(): ?ValidatorInterface
    {
        if (null === $this->validator && ! $this->disableInArrayValidator()) {
            $this->validator = new InArrayValidator(options: [
                'haystack' => $this->getValueOptionsValues(),
                'strict'   => false,
            ]);
        }
        return $this->validator;
    }
}
