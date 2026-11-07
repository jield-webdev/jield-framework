<?php

declare(strict_types=1);

namespace Jield\Twig\Factory;

use InvalidArgumentException;
use Jield\Stdlib\Service;
use Jield\Stdlib\Value;
use Jield\Twig\ModuleOptions;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Twig\Loader;

use Twig\Loader\LoaderInterface;
use function is_string;

class ChainLoaderFactory implements FactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): Loader\ChainLoader {
        $moduleOptions = $options === null
            ? Service::get($container, ModuleOptions::class)
            : new ModuleOptions(Value::stringKeyedArray($options));

        // Setup loader
        $chain = new Loader\ChainLoader();

        /** @var mixed $loader */
        foreach ($moduleOptions->getLoaderChain() as $loader) {
            if (!is_string(value: $loader) || !$container->has($loader)) {
                throw new InvalidArgumentException(message: 'Loaders should be a service manager alias.');
            }
            $chain->addLoader(loader: Value::instance(
                $container->get($loader),
                LoaderInterface::class,
            ));
        }

        return $chain;
    }
}
