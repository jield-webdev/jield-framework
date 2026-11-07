<?php

declare(strict_types=1);

namespace Jield\Recaptcha\Form\Element;

use Jield\Form\Element;
use Jield\Recaptcha\Form\Validator\RecaptchaValidator;
use Laminas\InputFilter\InputProviderInterface;
use Override;
use SensitiveParameter;

class Recaptcha extends Element implements InputProviderInterface
{
    public const string ELEMENT_TYPE = 'recaptcha';

    protected array $attributes = [
        'type' => self::ELEMENT_TYPE,
    ];

    public function __construct(
        #[SensitiveParameter]
        private readonly string $secret,
    ) {
        parent::__construct();
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    #[Override]
    public function getInputSpecification(): array
    {
        $spec = [
            'required' => true,
            'validators' => [
                ['name' => RecaptchaValidator::class],
            ],
        ];
        $name = $this->getName();
        if ($name !== null) {
            $spec['name'] = $name;
        }

        return $spec;
    }
}
