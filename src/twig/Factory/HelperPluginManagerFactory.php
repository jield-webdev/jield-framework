<?php

declare(strict_types=1);

namespace Jield\Twig\Factory;

use Jield\Twig\ModuleOptions;
use Jield\Twig\View\HelperPluginManager;
use Laminas\ServiceManager\ConfigInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\View\Exception;
use Psr\Container\ContainerInterface;
use function class_exists;
use function is_string;
use function sprintf;

class HelperPluginManagerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null)
    {
        if (null === $options) {
            /** @var ModuleOptions $options */
            $options = $container->get(ModuleOptions::class);
        }

        $managerOptions = $options->getHelperManager();
        $managerConfigs = $managerOptions['configs'] ?? [];

        $viewHelper = new HelperPluginManager($container, $managerOptions);

        foreach ($managerConfigs as $configClass) {
            if (is_string($configClass) && class_exists($configClass)) {
                /** @var ConfigInterface $config */
                $config = new $configClass();

                if (!$config instanceof ConfigInterface) {
                    throw new Exception\RuntimeException(
                        sprintf(
                            'Invalid service manager configuration class provided; received "%s",
                                expected class implementing %s',
                            $configClass,
                            ConfigInterface::class
                        )
                    );
                }

                $config->configureServiceManager($viewHelper);
            }
        }

        return $viewHelper;
    }
}
