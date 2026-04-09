<?php

declare(strict_types=1);

namespace Jield\I18n\Translator;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\ServiceManager\ServiceManager;
use Psr\Container\ContainerInterface;

use function array_replace_recursive;
use function assert;
use function is_array;
use function is_iterable;
use function iterator_to_array;

/**
 * @internal
 *
 * @psalm-internal Jield\I18n
 * @psalm-internal JieldTest\I18n
 * @psalm-import-type ServiceManagerConfiguration from ServiceManager
 */
final readonly class LoaderPluginManagerFactory implements FactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): LoaderPluginManager {
        $options ??= [];
        /** @psalm-var mixed $config */
        $config = $container->has(id: 'config') ? $container->get('config') : [];
        $config = ! is_iterable(value: $config) ? [] : $config;
        $config = iterator_to_array(iterator: $config);

        $plugins = $config['translator_plugins'] ?? [];
        assert(assertion: is_array(value: $plugins));

        // Merge arguments to build() over plugins in `config`
        $plugins = array_replace_recursive($plugins, $options);
        /** @psalm-var ServiceManagerConfiguration $plugins */

        return new LoaderPluginManager(creationContext: $container, config: $plugins);
    }
}
