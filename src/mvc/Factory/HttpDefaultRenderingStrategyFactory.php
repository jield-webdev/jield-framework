<?php

declare(strict_types=1);

namespace Jield\Mvc\Factory;

// phpcs:ignore
use ArrayAccess;
use Jield\Mvc\Service\HttpViewManagerConfigTrait;
use Jield\Mvc\View\Http\DefaultRenderingStrategy;
use Jield\Stdlib\Service;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\View\View;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

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
        $strategy = new DefaultRenderingStrategy(Service::get($container, View::class));
        $config = $this->getConfig($container);

        $this->injectLayoutTemplate($strategy, $config);

        return $strategy;
    }

    /**
     * Inject layout template.
     *
     * Uses layout template from configuration; if none available, defaults to "layout/layout".
     */
    private function injectLayoutTemplate(DefaultRenderingStrategy $strategy, array|ArrayAccess $config): void
    {
        /** @mago-var mixed $layout */
        $layout = $config['layout'] ?? 'layout/layout';
        $strategy->setLayoutTemplate(Assert::string($layout));
    }
}
