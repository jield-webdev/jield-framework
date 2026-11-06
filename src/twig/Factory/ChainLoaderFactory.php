<?php

declare(strict_types=1);

namespace Jield\Twig\Factory;

use InvalidArgumentException;
use Jield\Twig\ModuleOptions;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Twig\Loader;

use function is_string;

class ChainLoaderFactory implements FactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): Loader\ChainLoader {
        $moduleOptions = $options === null
            ? \Jield\Stdlib\Service::get($container, ModuleOptions::class)
            : new ModuleOptions(\Jield\Stdlib\Value::stringKeyedArray($options));

        // Setup loader
        $chain = new Loader\ChainLoader();

        /** @var mixed $loader */
        foreach ($moduleOptions->getLoaderChain() as $loader) {
            if (!is_string(value: $loader) || !$container->has($loader)) {
                throw new InvalidArgumentException(message: 'Loaders should be a service manager alias.');
            }
            $chain->addLoader(loader: \Jield\Stdlib\Value::instance($container->get($loader), \Twig\Loader\LoaderInterface::class));
        }

        return $chain;
    }
}
