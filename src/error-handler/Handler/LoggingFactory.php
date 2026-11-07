<?php

declare(strict_types=1);

namespace Jield\ErrorHandler\Handler;

use Jield\Stdlib\Service;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;

final class LoggingFactory
{
    public function __invoke(ContainerInterface $container): Logging
    {
        $config = $container->get('config')['error-hero-module'];
        $mail = $config['email-notification-settings'];
        return new Logging(
            Service::get($container, LoggerInterface::class),
            $config,
            $mail['enable'] ?? false ? Service::get($container, MailerInterface::class) : null,
            $mail['include-files-to-attachments'] ?? true,
        );
    }
}
