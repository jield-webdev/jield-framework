<?php

declare(strict_types=1);

namespace Jield\ErrorHandler\Handler;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;

final class LoggingFactory
{
    public function __invoke(ContainerInterface $container): Logging
    {
        /** @var mixed $config */
        $config = \Jield\Stdlib\Value::accessible($container->get('config'))['error-hero-module'];
        /** @var mixed $mail */
        $mail = \Jield\Stdlib\Value::accessible($config)['email-notification-settings'];
        return new Logging(
            \Jield\Stdlib\Service::get($container, LoggerInterface::class),
            \Jield\Stdlib\Value::array($config),
            $mail['enable'] ?? false ? \Jield\Stdlib\Service::get($container, MailerInterface::class) : null,
            \Jield\Stdlib\Value::bool($mail['include-files-to-attachments'] ?? true),
        );
    }
}
