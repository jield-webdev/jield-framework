<?php

declare(strict_types=1);

namespace Jield\Bootstrap5\View\Helper;

use BadMethodCallException;
use InvalidArgumentException;
use Jield\Bootstrap5\View\Helper\Navigation\Menu;
use Laminas\Navigation\AbstractContainer;
use Laminas\Navigation\Navigation as NavigationContainer;
use Psr\Container\ContainerInterface;

use function is_string;
use function sprintf;

final class Navigation
{
    private ?AbstractContainer $container = null;

    public function __construct(
        private readonly Menu $menuHelper,
        private readonly ContainerInterface $services
    ) {
    }

    public function __invoke(AbstractContainer|string|null $container = null): self
    {
        $this->container = $this->resolveContainer($container);

        return $this;
    }

    /**
     * Render a bootstrap5 menu for the resolved navigation container.
     *
     * @param AbstractContainer|string|null $container
     * @param array<string, mixed> $options
     */
    public function menu(AbstractContainer|string|null $container = null, array $options = []): string
    {
        $resolved = $this->resolveContainer($container ?? $this->container);
        if ($resolved === null) {
            throw new InvalidArgumentException('No navigation container available to render.');
        }

        return ($this->menuHelper)($resolved, $options);
    }

    /**
     * Backwards-compatible proxy for "lbs5menu".
     *
     * @param array<int, mixed> $arguments
     */
    public function __call(string $name, array $arguments): string
    {
        if ($name === 'lbs5menu') {
            return $this->menu(...$arguments);
        }

        throw new BadMethodCallException(sprintf('Unknown navigation helper method "%s".', $name));
    }

    private function resolveContainer(AbstractContainer|string|null $container): ?AbstractContainer
    {
        if ($container instanceof AbstractContainer) {
            return $container;
        }

        if (is_string($container)) {
            /** @var AbstractContainer $resolved */
            $resolved = $this->services->get($container);
            return $resolved;
        }

        if ($this->services->has('navigation')) {
            /** @var AbstractContainer $resolved */
            $resolved = $this->services->get('navigation');
            return $resolved;
        }

        if ($this->services->has(NavigationContainer::class)) {
            /** @var AbstractContainer $resolved */
            $resolved = $this->services->get(NavigationContainer::class);
            return $resolved;
        }

        if ($this->services->has('Navigation')) {
            /** @var AbstractContainer $resolved */
            $resolved = $this->services->get('Navigation');
            return $resolved;
        }

        return null;
    }
}
