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
        if (null === $options) {
            /** @var ModuleOptions $options */
            $options = $container->get(ModuleOptions::class);
        }

        $envClass = $options->getEnvironmentClass() ?: Environment::class;

        if (!$container->has($options->getEnvironmentLoader())) {
            throw new RuntimeException(
                sprintf(
                    'Loader with alias "%s" could not be found!',
                    $options->getEnvironmentLoader()
                )
            );
        }

        /** @var Environment $env */
        $env = new $envClass($container->get($options->getEnvironmentLoader()), $options->getEnvironmentOptions());

        if ($options->enableFallbackFunctions()) {
            $helperPluginManager = $container->get('ViewHelperManager');
            $env->registerUndefinedFunctionCallback(
                function ($name) use ($helperPluginManager) {
                    if ($helperPluginManager->has($name)) {
                        return new TwigFunction(
                            $name,
                            [$helperPluginManager->get($name), '__invoke'],
                            ['is_safe' => ['all']]
                        );
                    }
                    return false;
                }
            );
        }

        foreach ($options->getGlobals() as $name => $value) {
            $env->addGlobal($name, $value);
        }

        // Extensions are loaded later to avoid circular dependencies (for example, if an extension needs Renderer).
        return $env;
    }
}
