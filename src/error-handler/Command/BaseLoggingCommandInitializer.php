<?php

declare(strict_types=1);

namespace Jield\ErrorHandler\Command;

use Jield\ErrorHandler\Handler\Logging;
use Jield\Stdlib\Service;
use Laminas\ServiceManager\Initializer\InitializerInterface;
use Override;
use Psr\Container\ContainerInterface;

final class BaseLoggingCommandInitializer implements InitializerInterface
{
    #[Override]
    public function __invoke(ContainerInterface $container, mixed $instance): void
    {
        if (!$instance instanceof BaseLoggingCommand) {
            return;
        }

        /** @var array $config */
        $config = $container->get('config');
        $errorHeroModuleConfig = $config['error-hero-module'];

        $logging = Service::get($container, Logging::class);
        $instance->init(errorHeroModuleConfig: $errorHeroModuleConfig, logging: $logging);
    }
}
