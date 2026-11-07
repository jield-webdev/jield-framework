<?php

declare(strict_types=1);

namespace Jield\ErrorHandler\Handler;

use Jield\Stdlib\Service;
use Jield\Stdlib\Value;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;

final class LoggingFactory
{
    public function __invoke(ContainerInterface $container): Logging
    {
        $config = Value::accessible($container->get('config'))['error-hero-module'];
        $mail = Value::accessible($config)['email-notification-settings'];
        return new Logging(
            Service::get($container, LoggerInterface::class),
            Value::array($config),
            $mail['enable'] ?? false ? Service::get($container, MailerInterface::class) : null,
            Value::bool($mail['include-files-to-attachments'] ?? true),
        );
    }
}
