<?php

declare(strict_types=1);

namespace Jield\Mvc\Factory;

// phpcs:ignore
use ArrayAccess;
use Jield\Mvc\Service\HttpViewManagerConfigTrait;
use Jield\Mvc\View\Http\RouteNotFoundStrategy;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

class HttpRouteNotFoundStrategyFactory implements FactoryInterface
{
    use HttpViewManagerConfigTrait;

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
    private function injectDisplayExceptions(RouteNotFoundStrategy $strategy, array|ArrayAccess $config): void
    {
        /** @mago-var mixed $flag */
        $flag = $config['display_exceptions'] ?? false;
        $strategy->setDisplayExceptions(Assert::boolean($flag));
    }

    /**
     * Inject strategy with configured display_not_found_reason flag.
     */
    private function injectDisplayNotFoundReason(RouteNotFoundStrategy $strategy, array|ArrayAccess $config): void
    {
        /** @mago-var mixed $flag */
        $flag = $config['display_not_found_reason'] ?? false;
        $strategy->setDisplayNotFoundReason(Assert::boolean($flag));
    }

    /**
     * Inject strategy with configured not_found_template.
     */
    private function injectNotFoundTemplate(RouteNotFoundStrategy $strategy, array|ArrayAccess $config): void
    {
        /** @mago-var mixed $template */
        $template = $config['not_found_template'] ?? '404';
        $strategy->setNotFoundTemplate(Assert::string($template));
    }
}
