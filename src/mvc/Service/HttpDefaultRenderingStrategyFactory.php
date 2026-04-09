<?php

namespace Jield\Mvc\Service;

// phpcs:ignore
use Psr\Container\ContainerInterface;
use Jield\Mvc\View\Http\DefaultRenderingStrategy;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\View\View;

class HttpDefaultRenderingStrategyFactory implements FactoryInterface
{
    use HttpViewManagerConfigTrait;

    /**
     * @param  string $requestedName
     * @param  null|array $options
     * @return DefaultRenderingStrategy
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): mixed
    {
        $strategy = new DefaultRenderingStrategy(view: $container->get(View::class));
        $config   = $this->getConfig(container: $container);

        $this->injectLayoutTemplate(strategy: $strategy, config: $config);

        return $strategy;
    }

    /**
     * Inject layout template.
     *
     * Uses layout template from configuration; if none available, defaults to "layout/layout".
     */
    private function injectLayoutTemplate(DefaultRenderingStrategy $strategy, array $config): void
    {
        $layout = $config['layout'] ?? 'layout/layout';
        $strategy->setLayoutTemplate(layoutTemplate: $layout);
    }
}
