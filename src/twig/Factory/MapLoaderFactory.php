<?php

declare(strict_types=1);

namespace Jield\Twig\Factory;

use Jield\Stdlib\Service;
use Jield\Twig\MapLoader;
use Jield\Twig\ModuleOptions;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

use function pathinfo;

use const PATHINFO_EXTENSION;

class MapLoaderFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): MapLoader
    {
        $moduleOptions = $options === null
            ? Service::get($container, ModuleOptions::class)
            : new ModuleOptions(\Jield\Stdlib\Value::options($options));

        $laminasTemplateMap = Service::get(
            $container,
            \Laminas\View\Resolver\TemplateMapResolver::class,
            'ViewTemplateMapResolver',
        );

        $templateMap = new MapLoader();
        foreach ($laminasTemplateMap as $name => $path) {
            if ($moduleOptions->getSuffix() !== pathinfo(path: $path, flags: PATHINFO_EXTENSION)) {
                continue;
            }

            $templateMap->add(name: $name, path: $path);
        }

        return $templateMap;
    }
}
