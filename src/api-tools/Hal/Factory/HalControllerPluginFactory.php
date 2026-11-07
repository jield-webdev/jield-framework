<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Factory;

use Jield\ApiTools\Hal\Plugin\Hal;
use Jield\Stdlib\Service;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\View\HelperPluginManager;
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
        $helpers = Service::get(
            $container,
            HelperPluginManager::class,
            'ViewHelperManager',
        );
        /** @psalm-var Hal */
        return $helpers->get('Hal');
    }
}
