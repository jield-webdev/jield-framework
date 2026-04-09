<?php

namespace Jield\Mvc\Service;

// phpcs:ignore
use Psr\Container\ContainerInterface;
use Jield\Mvc\View\Http\RouteNotFoundStrategy;
use Laminas\ServiceManager\Factory\FactoryInterface;

class HttpRouteNotFoundStrategyFactory implements FactoryInterface
{
    use HttpViewManagerConfigTrait;

    /**
     * @param  string $requestedName
     * @param  null|array $options
     * @return RouteNotFoundStrategy
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): mixed
    {
        $strategy = new RouteNotFoundStrategy();
        $config   = $this->getConfig(container: $container);

        $this->injectDisplayExceptions(strategy: $strategy, config: $config);
        $this->injectDisplayNotFoundReason(strategy: $strategy, config: $config);
        $this->injectNotFoundTemplate(strategy: $strategy, config: $config);

        return $strategy;
    }

    /**
     * Inject strategy with configured display_exceptions flag.
     */
    private function injectDisplayExceptions(RouteNotFoundStrategy $strategy, array $config): void
    {
        $flag = $config['display_exceptions'] ?? false;
        $strategy->setDisplayExceptions(displayExceptions: $flag);
    }

    /**
     * Inject strategy with configured display_not_found_reason flag.
     */
    private function injectDisplayNotFoundReason(RouteNotFoundStrategy $strategy, array $config): void
    {
        $flag = $config['display_not_found_reason'] ?? false;
        $strategy->setDisplayNotFoundReason(displayNotFoundReason: $flag);
    }

    /**
     * Inject strategy with configured not_found_template.
     */
    private function injectNotFoundTemplate(RouteNotFoundStrategy $strategy, array $config): void
    {
        $template = $config['not_found_template'] ?? '404';
        $strategy->setNotFoundTemplate(notFoundTemplate: $template);
    }
}
