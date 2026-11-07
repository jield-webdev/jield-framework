<?php

declare(strict_types=1);

namespace Jield\ErrorHandler\Handler;

use Jield\Stdlib\Service;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;

final class LoggingFactory
{
    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): Logging
    {
        /** @mago-var mixed $config */
        $config = \Jield\Stdlib\Value::accessible($container->get('config'))['error-hero-module'];
        /** @mago-var mixed $mail */
        $mail = \Jield\Stdlib\Value::accessible($config)['email-notification-settings'];
        return new Logging(
            Service::get($container, LoggerInterface::class),
            \Webmozart\Assert\Assert::isArray($config),
            $mail['enable'] ?? false ? Service::get($container, MailerInterface::class) : null,
            \Webmozart\Assert\Assert::boolean($mail['include-files-to-attachments'] ?? true),
        );
    }
}
