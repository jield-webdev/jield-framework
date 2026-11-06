<?php

declare(strict_types=1);

namespace Jield\Twig;

use InvalidArgumentException;
use Jield\ModuleManager\Feature\BootstrapListenerInterface;
use Jield\ModuleManager\Feature\ConfigProviderInterface;
use Laminas\EventManager\EventInterface;
use Twig\Environment;

use function is_object;
use function is_string;

class Module implements BootstrapListenerInterface, ConfigProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function onBootstrap(EventInterface $e): void
    {
        $application = \Jield\Stdlib\Value::present(\Jield\Stdlib\Value::instance($e, \Jield\Mvc\MvcEvent::class)->getApplication());
        /** @var mixed $serviceManager */
        $serviceManager = $application->getServiceManager();
        $environment = \Jield\Stdlib\Service::get(\Jield\Stdlib\Value::instance($serviceManager, \Psr\Container\ContainerInterface::class), Environment::class);

        
        $options = \Jield\Stdlib\Service::get(\Jield\Stdlib\Value::instance($serviceManager, \Psr\Container\ContainerInterface::class), ModuleOptions::class);

        // Setup extensions
        /** @var mixed $extension */
        foreach ($options->getExtensions() as $extension) {
            // Allows modules to override/remove extensions.
            if (empty($extension)) {
                continue;
            }

            if (is_string($extension)) {
                if ($serviceManager->has($extension)) {
                    /** @var mixed $extension */
                    $extension = $serviceManager->get($extension);
                } else {
                    $extension = \Jield\Stdlib\Value::instantiate($extension);
                }
            } elseif (!is_object($extension)) {
                throw new InvalidArgumentException('Extensions should be a string or object.');
            }

            $environment->addExtension(\Jield\Stdlib\Value::instance($extension, \Twig\Extension\ExtensionInterface::class));
        }
    }

    /**
     * @return array
     */
    public function getConfig(): array
    {
        return new ConfigProvider()();
    }
}
