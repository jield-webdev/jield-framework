<?php

declare(strict_types=1);

namespace Jield\Mvc\Factory;

// phpcs:ignore
use Jield\Mvc\Service\HttpViewManagerConfigTrait;
use Jield\Mvc\View\Http\RouteNotFoundStrategy;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class HttpRouteNotFoundStrategyFactory implements FactoryInterface
{
    use HttpViewManagerConfigTrait;

    /**
     * @param  string $requestedName
     * @param  null|array $options
     * @return RouteNotFoundStrategy
     */
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): RouteNotFoundStrategy {
        $strategy = new RouteNotFoundStrategy();
        $config = $this->getConfig($container);

        $this->injectDisplayExceptions($strategy, $config);
        $this->injectDisplayNotFoundReason($strategy, $config);
        $this->injectNotFoundTemplate($strategy, $config);

        return $strategy;
    }

    /**
     * Inject strategy with configured display_exceptions flag.
     */
    private function injectDisplayExceptions(RouteNotFoundStrategy $strategy, array $config): void
    {
        /** @var mixed $flag */
        $flag = $config['display_exceptions'] ?? false;
        $strategy->setDisplayExceptions(\Jield\Stdlib\Value::bool($flag));
    }

    /**
     * Inject strategy with configured display_not_found_reason flag.
     */
    private function injectDisplayNotFoundReason(RouteNotFoundStrategy $strategy, array $config): void
    {
        /** @var mixed $flag */
        $flag = $config['display_not_found_reason'] ?? false;
        $strategy->setDisplayNotFoundReason(\Jield\Stdlib\Value::bool($flag));
    }

    /**
     * Inject strategy with configured not_found_template.
     */
    private function injectNotFoundTemplate(RouteNotFoundStrategy $strategy, array $config): void
    {
        /** @var mixed $template */
        $template = $config['not_found_template'] ?? '404';
        $strategy->setNotFoundTemplate(\Jield\Stdlib\Value::string($template));
    }
}
