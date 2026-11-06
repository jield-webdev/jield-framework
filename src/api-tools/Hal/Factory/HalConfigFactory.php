<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Factory;

use Psr\Container\ContainerInterface;

use function is_array;

class HalConfigFactory
{
    public function __invoke(ContainerInterface $container): array
    {
        /** @var array<string,mixed> $config */
        $config = $container->has('config') ? $container->get('config') : [];

        return (
            isset($config['api-tools-hal']) && is_array(value: $config['api-tools-hal']) ? $config['api-tools-hal'] : []
        );
    }
}
