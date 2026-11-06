<?php

declare(strict_types=1);

namespace Jield\Recaptcha\Factory\Form\Element;

use Jield\Recaptcha\Form\Element\Recaptcha;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;

class RecaptchaFactory implements FactoryInterface
{
    #[Override]
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Recaptcha
    {
        /** @var array{circlical?: array{recaptcha?: array{client?: string}}} $config */
        $config = $container->get('config');

        return new Recaptcha(secret: $config['circlical']['recaptcha']['client'] ?? 'configure_me');
    }
}
