<?php

declare(strict_types=1);

namespace Jield\Twig\Factory;

use Jield\Stdlib\Service;
use Jield\Stdlib\Value;
use Jield\Twig\ModuleOptions;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\View\HelperPluginManager;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Twig\Environment;
use Twig\Loader\LoaderInterface;
use Twig\TwigFunction;

use function sprintf;

class EnvironmentFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): Environment
    {
        $moduleOptions = $options === null
            ? Service::get($container, ModuleOptions::class)
            : new ModuleOptions(Value::stringKeyedArray($options));

        $envClass = $moduleOptions->getEnvironmentClass() ?: Environment::class;

        if (!$container->has($moduleOptions->getEnvironmentLoader())) {
            throw new RuntimeException(sprintf(
                'Loader with alias "%s" could not be found!',
                $moduleOptions->getEnvironmentLoader(),
            ));
        }

        $env = Value::instance(Value::instantiate($envClass, [
            Value::instance(
                $container->get($moduleOptions->getEnvironmentLoader()),
                LoaderInterface::class,
            ),
            $moduleOptions->getEnvironmentOptions(),
        ]), Environment::class);

        if ($moduleOptions->enableFallbackFunctions()) {
            $helperPluginManager = Service::get(
                $container,
                HelperPluginManager::class,
                'ViewHelperManager',
            );
            $env->registerUndefinedFunctionCallback(static function ($name) use ($helperPluginManager) {
                if ($helperPluginManager->has($name)) {
                    return new TwigFunction(
                        $name,
                        Value::callback($helperPluginManager->get($name)),
                        ['is_safe' => ['all']],
                    );
                }
                return false;
            });
        }

        foreach ($moduleOptions->getGlobals() as $name => $value) {
            $env->addGlobal(Value::string($name), $value);
        }

        // Extensions are loaded later to avoid circular dependencies (for example, if an extension needs Renderer).
        return $env;
    }
}
