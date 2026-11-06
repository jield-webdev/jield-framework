<?php

declare(strict_types=1);

namespace Jield\ErrorHandler\Command;

use Jield\ErrorHandler\Handler\Logging;
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
        /** @var mixed $errorHeroModuleConfig */
        $errorHeroModuleConfig = $config['error-hero-module'];

        
        $logging = \Jield\Stdlib\Service::get($container, Logging::class);
        $instance->init(errorHeroModuleConfig: \Jield\Stdlib\Value::array($errorHeroModuleConfig), logging: $logging);
    }
}
