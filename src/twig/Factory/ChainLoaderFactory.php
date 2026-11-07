<?php

declare(strict_types=1);

namespace Jield\Twig\Factory;

use InvalidArgumentException;
use Jield\Stdlib\Service;
use Jield\Twig\ModuleOptions;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Twig\Loader;

class ChainLoaderFactory implements FactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): Loader\ChainLoader {
        $moduleOptions = $options === null
            ? Service::get($container, ModuleOptions::class)
            : new ModuleOptions(\Jield\Stdlib\Value::options($options));

        // Setup loader
        $chain = new Loader\ChainLoader();

        foreach ($moduleOptions->getLoaderChain() as $loader) {
            if (!$container->has($loader)) {
                throw new InvalidArgumentException(message: 'Loaders should be a service manager alias.');
            }
            $chain->addLoader(loader: \Webmozart\Assert\Assert::isInstanceOf($container->get($loader), \Twig\Loader\LoaderInterface::class));
        }

        return $chain;
    }
}
