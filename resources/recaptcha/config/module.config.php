<?php

namespace Jield\Recaptcha;


use Jield\Recaptcha\Factory\Form\Element\RecaptchaFactory;
use Jield\Recaptcha\Factory\Validator\RecaptchaValidatorFactory;
use Jield\Recaptcha\Form\Element\Recaptcha;
use Jield\Recaptcha\Form\Validator\RecaptchaValidator;
use Jield\Recaptcha\Form\View\Helper\Recaptcha as RecaptchaHelper;


return [
    'form_elements' => [
        'factories' => [
            Recaptcha::class => RecaptchaFactory::class,
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'recaptcha' => RecaptchaHelper::class,
        ],
    ],
    'validators' => [
        'factories' => [
            RecaptchaValidator::class => RecaptchaValidatorFactory::class,
        ],
    ],
];