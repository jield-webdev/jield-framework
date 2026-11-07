<?php

declare(strict_types=1);

namespace Jield\Recaptcha\Factory\Validator;

use Jield\Recaptcha\Form\Validator\RecaptchaValidator;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;

class RecaptchaValidatorFactory implements FactoryInterface
{
    private const int DEFAULT_TIMEOUT = 900;

    #[Override]
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): RecaptchaValidator
    {
        /** @var array{circlical: array{recaptcha: array{server?: string, default_timeout?: int, bypass?: bool}}} $config */
        $config = $container->get('config');
        $config = $config['circlical']['recaptcha'];

        $validator = new RecaptchaValidator(
            secret: $config['server'] ?? 'not configured',
            responseTimeout: $config['default_timeout'] ?? self::DEFAULT_TIMEOUT,
        );

        if (!!($config['bypass'] ?? null)) {
            $validator->setCaptchaBypassed(captchaBypassed: $config['bypass']);
        }

        return $validator;
    }
}
