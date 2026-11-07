<?php

declare(strict_types=1);

namespace Jield\Twig;

use InvalidArgumentException;
use Jield\ModuleManager\Feature\BootstrapListenerInterface;
use Jield\ModuleManager\Feature\ConfigProviderInterface;
use Jield\Mvc\MvcEvent;
use Jield\Stdlib\Service;
use Jield\Stdlib\Value;
use Laminas\EventManager\EventInterface;
use Psr\Container\ContainerInterface;
use Twig\Environment;

use Twig\Extension\ExtensionInterface;
use function is_object;
use function is_string;

class Module implements BootstrapListenerInterface, ConfigProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function onBootstrap(EventInterface $e): void
    {
        $application = Value::present(
            Value::instance($e, MvcEvent::class)->getApplication(),
        );
        /** @var mixed $serviceManager */
        $serviceManager = $application->getServiceManager();
        $environment = Service::get(
            Value::instance($serviceManager, ContainerInterface::class),
            Environment::class,
        );

        $options = Service::get(
            Value::instance($serviceManager, ContainerInterface::class),
            ModuleOptions::class,
        );

        // Setup extensions
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
                    $extension = Value::instantiate($extension);
                }
            } elseif (!is_object($extension)) {
                throw new InvalidArgumentException('Extensions should be a string or object.');
            }

            $environment->addExtension(Value::instance(
                $extension,
                ExtensionInterface::class,
            ));
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
