<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Factory;

use Jield\ApiTools\Hal\Plugin\Hal;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;

class HalControllerPluginFactory implements FactoryInterface
{
    /**
     * @param string $requestedName
     */
    #[Override]
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Hal
    {
        $helpers = \Jield\Stdlib\Service::get($container, \Laminas\View\HelperPluginManager::class, 'ViewHelperManager');
        /** @psalm-var Hal */
        return $helpers->get('Hal');
    }
}
