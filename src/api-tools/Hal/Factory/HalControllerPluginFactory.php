<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Factory;


use Override;
use Psr\Container\ContainerInterface;
use Jield\ApiTools\Hal\Plugin\Hal;
use Laminas\ServiceManager\Factory\FactoryInterface;

class HalControllerPluginFactory implements FactoryInterface
{
    /**
     * @param string $requestedName
     */
    #[Override]
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): Hal
    {
        $helpers = $container->get('ViewHelperManager');
        /** @psalm-var Hal */
        return $helpers->get('Hal');
    }
}
