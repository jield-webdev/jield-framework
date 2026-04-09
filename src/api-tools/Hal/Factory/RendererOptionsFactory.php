<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Factory;


use Psr\Container\ContainerInterface;
use Jield\ApiTools\Hal\RendererOptions;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function is_array;

class RendererOptionsFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): RendererOptions
    {
        $config = $container->get('Jield\ApiTools\Hal\HalConfig');

        $rendererConfig = isset($config['renderer']) && is_array(value: $config['renderer'])
            ? $config['renderer']
            : [];

        if (
            isset($rendererConfig['render_embedded_resources'])
            && ! isset($rendererConfig['render_embedded_entities'])
        ) {
            $rendererConfig['render_embedded_entities'] = $rendererConfig['render_embedded_resources'];
        }

        return new RendererOptions(options: $rendererConfig);
    }
}
