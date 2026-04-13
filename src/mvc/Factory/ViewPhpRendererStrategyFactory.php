<?php

namespace Jield\Mvc\Factory;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\View\Renderer\PhpRenderer;
use Psr\Container\ContainerInterface;

class ViewPhpRendererStrategyFactory implements FactoryInterface
{
    /**
     * @param  string $requestedName
     * @param  null|array $options
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null)
    {
        if (!class_exists(\Laminas\View\Strategy\PhpRendererStrategy::class)) {
            throw new ServiceNotCreatedException(
                'Laminas\\View\\Strategy\\PhpRendererStrategy is not available in laminas-view v3; '
                . 'install laminas/laminas-mvc or provide a compatible strategy.'
            );
        }

        $strategyClass = \Laminas\View\Strategy\PhpRendererStrategy::class;
        return new $strategyClass($container->get(PhpRenderer::class));
    }
}
