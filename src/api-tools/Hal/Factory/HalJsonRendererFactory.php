<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Factory;


use Jield\ApiTools\ApiProblem\View\ApiProblemRenderer;
use Jield\ApiTools\Hal\View\HalJsonRenderer;
use Laminas\View\HelperPluginManager;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

class HalJsonRendererFactory
{
    public function __invoke(ContainerInterface $container): HalJsonRenderer
    {
        $helpers = $container->get('ViewHelperManager');
        Assert::isInstanceOf(value: $helpers, class: HelperPluginManager::class);
        $apiProblemRenderer = $container->get(ApiProblemRenderer::class);
        Assert::isInstanceOf(value: $apiProblemRenderer, class: ApiProblemRenderer::class);

        $renderer = new HalJsonRenderer(apiProblemRenderer: $apiProblemRenderer);
        $renderer->setHelperPluginManager(helpers: $helpers);

        return $renderer;
    }
}
