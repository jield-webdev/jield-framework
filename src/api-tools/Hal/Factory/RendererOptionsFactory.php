<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Factory;

use Jield\ApiTools\Hal\RendererOptions;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

use function is_array;

class RendererOptionsFactory
{
    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): RendererOptions
    {
        /** @mago-var mixed $config */
        $config = $container->get('Jield\ApiTools\Hal\HalConfig');

        $rendererConfig = ($config['renderer'] ?? null) !== null && is_array(value: $config['renderer'])
            ? $config['renderer']
            : [];

        if (
            ($rendererConfig['render_embedded_resources'] ?? null) !== null
            && ($rendererConfig['render_embedded_entities'] ?? null) === null
        ) {
            $rendererConfig['render_embedded_entities'] = $rendererConfig['render_embedded_resources'];
        }

        return new RendererOptions(options: \Jield\Stdlib\Value::options($rendererConfig));
    }
}
