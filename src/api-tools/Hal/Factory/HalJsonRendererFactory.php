<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Factory;

use Jield\ApiTools\ApiProblem\View\ApiProblemRenderer;
use Jield\ApiTools\Hal\View\HalJsonRenderer;
use Jield\Stdlib\Service;
use Laminas\View\HelperPluginManager;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

class HalJsonRendererFactory
{
    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): HalJsonRenderer
    {
        $helpers = Service::get($container, \Laminas\View\HelperPluginManager::class, 'ViewHelperManager');
        $apiProblemRenderer = Service::get($container, ApiProblemRenderer::class);

        $renderer = new HalJsonRenderer(apiProblemRenderer: $apiProblemRenderer);
        $renderer->setHelperPluginManager(helpers: $helpers);

        return $renderer;
    }
}
