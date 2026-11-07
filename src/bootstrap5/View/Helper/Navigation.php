<?php

declare(strict_types=1);

namespace Jield\Bootstrap5\View\Helper;

use InvalidArgumentException;
use Jield\Bootstrap5\View\Helper\Navigation\Menu;
use Jield\Stdlib\Service;
use Jield\Stdlib\Value;
use Laminas\Navigation\AbstractContainer;
use Laminas\Navigation\Navigation as NavigationContainer;
use Laminas\View\HelperPluginManager;
use Psr\Container\ContainerInterface;

use function is_string;

final class Navigation
{
    private ?AbstractContainer $container = null;

    public function __construct(
        private readonly Menu $menuHelper,
        private readonly ContainerInterface $services,
    ) {}

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
    public function __call(string $name, array $arguments): mixed
    {
        $proxy = Service::get(
            $this->services,
            HelperPluginManager::class,
            'ViewHelperManager',
        )->get('navigation');
        $proxy = Value::instance($proxy, \Jield\Mvc\Navigation\View\Navigation::class);
        $proxy($this->resolveContainer($this->container));

        return Value::call($proxy, $name === 'lbs5menu' ? 'menu' : $name, $arguments);
    }

    private function resolveContainer(AbstractContainer|string|null $container): ?AbstractContainer
    {
        if ($container instanceof AbstractContainer) {
            return $container;
        }

        if (is_string($container)) {
            /** @var AbstractContainer $resolved */
            return Value::nullableInstance(
                $this->services->get($container),
                \Laminas\Navigation\AbstractContainer::class,
            );
        }

        if ($this->services->has('navigation')) {
            /** @var AbstractContainer $resolved */
            return Value::nullableInstance(
                $this->services->get('navigation'),
                \Laminas\Navigation\AbstractContainer::class,
            );
        }

        if ($this->services->has(NavigationContainer::class)) {
            /** @var AbstractContainer $resolved */
            return Service::get($this->services, NavigationContainer::class);
        }

        if ($this->services->has('Navigation')) {
            /** @var AbstractContainer $resolved */
            return Value::nullableInstance(
                $this->services->get('Navigation'),
                \Laminas\Navigation\AbstractContainer::class,
            );
        }

        return null;
    }
}
