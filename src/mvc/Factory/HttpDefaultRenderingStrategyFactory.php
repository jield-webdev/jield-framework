<?php

declare(strict_types=1);

namespace Jield\Mvc\Factory;

// phpcs:ignore
use Jield\Mvc\Service\HttpViewManagerConfigTrait;
use Jield\Mvc\View\Http\DefaultRenderingStrategy;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\View\View;
use Psr\Container\ContainerInterface;

class HttpDefaultRenderingStrategyFactory implements FactoryInterface
{
    use HttpViewManagerConfigTrait;

    /**
     * @param  string $requestedName
     * @param  null|array $options
     * @return DefaultRenderingStrategy
     */
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): DefaultRenderingStrategy {
        $strategy = new DefaultRenderingStrategy(\Jield\Stdlib\Service::get($container, View::class));
        $config = $this->getConfig($container);

        $this->injectLayoutTemplate($strategy, $config);

        return $strategy;
    }

    /**
     * Inject layout template.
     *
     * Uses layout template from configuration; if none available, defaults to "layout/layout".
     */
    private function injectLayoutTemplate(DefaultRenderingStrategy $strategy, array $config): void
    {
        /** @var mixed $layout */
        $layout = $config['layout'] ?? 'layout/layout';
        $strategy->setLayoutTemplate(\Jield\Stdlib\Value::string($layout));
    }
}
