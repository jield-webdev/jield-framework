<?php

declare(strict_types=1);

namespace Jield\Twig\Factory;

use Jield\Twig\ModuleOptions;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Twig\Environment;
use Twig\TwigFunction;

use function sprintf;

class EnvironmentFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): Environment
    {
        $moduleOptions = $options === null
            ? \Jield\Stdlib\Service::get($container, ModuleOptions::class)
            : new ModuleOptions(\Jield\Stdlib\Value::stringKeyedArray($options));

        $envClass = $moduleOptions->getEnvironmentClass() ?: Environment::class;

        if (!$container->has($moduleOptions->getEnvironmentLoader())) {
            throw new RuntimeException(sprintf(
                'Loader with alias "%s" could not be found!',
                $moduleOptions->getEnvironmentLoader(),
            ));
        }

        
        $env = \Jield\Stdlib\Value::instance(\Jield\Stdlib\Value::instantiate($envClass, [\Jield\Stdlib\Value::instance($container->get($moduleOptions->getEnvironmentLoader()), \Twig\Loader\LoaderInterface::class), $moduleOptions->getEnvironmentOptions()]), Environment::class);

        if ($moduleOptions->enableFallbackFunctions()) {
            $helperPluginManager = \Jield\Stdlib\Service::get($container, \Laminas\View\HelperPluginManager::class, 'ViewHelperManager');
            $env->registerUndefinedFunctionCallback(static function ($name) use ($helperPluginManager) {
                if ($helperPluginManager->has($name)) {
                    return new TwigFunction(
                        $name,
                        \Jield\Stdlib\Value::callback($helperPluginManager->get($name)),
                        ['is_safe' => ['all']],
                    );
                }
                return false;
            });
        }

        /** @var mixed $value */
        foreach ($moduleOptions->getGlobals() as $name => $value) {
            $env->addGlobal(\Jield\Stdlib\Value::string($name), $value);
        }

        // Extensions are loaded later to avoid circular dependencies (for example, if an extension needs Renderer).
        return $env;
    }
}
