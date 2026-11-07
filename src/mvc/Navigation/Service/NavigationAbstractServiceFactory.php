<?php

declare(strict_types=1);

namespace Jield\Mvc\Navigation\Service;

use ArrayAccess;
use Laminas\Navigation\Navigation;
use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use Psr\Container\ContainerInterface;

use function is_array;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;

/**
 * Navigation abstract service factory
 *
 * Allows configuring several navigation instances. If you have a navigation config key named "special" then you can
 * use $container->get('Laminas\Navigation\Special') to retrieve a navigation instance with this configuration.
 */
final class NavigationAbstractServiceFactory implements AbstractFactoryInterface
{
    /**
     * Top-level configuration key indicating navigation configuration
     */
    public const string CONFIG_KEY = 'navigation';

    /**
     * Service manager factory prefix
     */
    public const string SERVICE_PREFIX = 'Laminas\\Navigation\\';

    /**
     * Navigation configuration
     *
     * @var array<string, mixed>|null
     */
    protected ?array $config = null;

    /**
     * Can we create a navigation by the requested name?
     *
     * @param string $requestedName Name by which service was requested, must
     *     start with Laminas\Navigation\
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function canCreate(ContainerInterface $container, string $requestedName): bool
    {
        if (!str_starts_with($requestedName, self::SERVICE_PREFIX)) {
            return false;
        }
        $config = $this->getConfig($container);

        return $this->hasNamedConfig($requestedName, $config);
    }

    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): Navigation
    {
        $config = $this->getConfig($container);
        $factory = new ConstructedNavigationFactory($this->getNamedConfig($requestedName, $config));
        return $factory($container, $requestedName);
    }

    /**
     * Get navigation configuration, if any
     *
     * @return array<string, mixed>
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function getConfig(ContainerInterface $container): array
    {
        if ($this->config !== null) {
            return $this->config;
        }

        if (!$container->has('config')) {
            $this->config = [];
            return $this->config;
        }

        /** @mago-var mixed $config */
        $config = $container->get('config');
        if (!isset($config[self::CONFIG_KEY]) || !is_array($config[self::CONFIG_KEY])) {
            $this->config = [];
            return $this->config;
        }

        $config = \Jield\Stdlib\Value::stringKeyedArray($config[self::CONFIG_KEY]);
        $this->config = $config;

        return $config;
    }

    /**
     * Extract config name from service name
     */
    private function getConfigName(string $name): string
    {
        return substr($name, strlen(self::SERVICE_PREFIX));
    }

    /**
     * Does the configuration have a matching named section?
     *
     * @param array<string, mixed>|ArrayAccess<string, mixed> $config
     */
    private function hasNamedConfig(string $name, array|ArrayAccess $config): bool
    {
        $withoutPrefix = $this->getConfigName($name);

        if (isset($config[$withoutPrefix])) {
            return true;
        }

        if (isset($config[strtolower($withoutPrefix)])) {
            return true;
        }

        return false;
    }

    /**
     * Get the matching named configuration section.
     *
     * @param array<string, mixed>|ArrayAccess<string, mixed> $config
     * @return array<array-key, array<string, mixed>>
     */
    private function getNamedConfig(string $name, array|ArrayAccess $config): array
    {
        $withoutPrefix = $this->getConfigName($name);

        if (isset($config[$withoutPrefix])) {
            return \Jield\Stdlib\Value::maps($config[$withoutPrefix]);
        }

        if (isset($config[strtolower($withoutPrefix)])) {
            return \Jield\Stdlib\Value::maps($config[strtolower($withoutPrefix)]);
        }

        return [];
    }
}
