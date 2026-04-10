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
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): Loader\ChainLoader
    {
        if (null === $options) {
            /** @var ModuleOptions $options */
            $options = $container->get(ModuleOptions::class);
        }

        // Setup loader
        $chain = new Loader\ChainLoader();

        foreach ($options->getLoaderChain() as $loader) {
            if (!is_string(value: $loader) || !$container->has(id: $loader)) {
                throw new InvalidArgumentException(message: 'Loaders should be a service manager alias.');
            }
            $chain->addLoader(loader: $container->get($loader));
        }

        return $chain;
    }
}
