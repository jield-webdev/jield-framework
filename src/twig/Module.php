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
use Twig\Environment;
use Webmozart\Assert\Assert;

use function is_object;
use function is_string;

class Module implements BootstrapListenerInterface, ConfigProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function onBootstrap(EventInterface $e): void
    {
        $application = Assert::notNull(Assert::isInstanceOf($e, MvcEvent::class)->getApplication());
        $serviceManager = $application->getServiceManager();
        $environment = Service::get($serviceManager, Environment::class);

        $options = Service::get($serviceManager, ModuleOptions::class);

        // Setup extensions
        /** @mago-var mixed $extension */
        foreach ($options->getExtensions() as $extension) {
            // Allows modules to override/remove extensions.
            if (empty($extension)) {
                continue;
            }

            if (is_string($extension)) {
                if ($serviceManager->has($extension)) {
                    /** @mago-var mixed $extension */
                    $extension = $serviceManager->get($extension);
                } else {
                    $extension = Value::instantiate($extension);
                }
            } elseif (!is_object($extension)) {
                throw new InvalidArgumentException('Extensions should be a string or object.');
            }

            $environment->addExtension(Assert::isInstanceOf($extension, \Twig\Extension\ExtensionInterface::class));
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
