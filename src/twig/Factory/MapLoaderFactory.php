<?php

declare(strict_types=1);

namespace Jield\Twig\Factory;

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
        if (null === $options) {
            /** @var ModuleOptions $options */
            $options = $container->get(ModuleOptions::class);
        }

        /** @var TemplateMapResolver $laminasTemplateMap */
        $laminasTemplateMap = $container->get('ViewTemplateMapResolver');

        $templateMap = new MapLoader();
        foreach ($laminasTemplateMap as $name => $path) {
            if ($options->getSuffix() === pathinfo((string)$path, PATHINFO_EXTENSION)) {
                $templateMap->add($name, (string)$path);
            }
        }

        return $templateMap;
    }
}
