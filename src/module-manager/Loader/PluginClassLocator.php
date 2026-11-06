<?php

declare(strict_types=1);

// phpcs:disable WebimpressCodingStandard.NamingConventions.Interface.Suffix

namespace Jield\ModuleManager\Loader;

use IteratorAggregate;
use Traversable;

/**
 * Plugin class locator interface
 
 *
 * @extends \IteratorAggregate<array-key, mixed>
 */
interface PluginClassLocator extends ShortNameLocator, IteratorAggregate
{
    /**
     * Register a class to a given short name
     *
     * @param  string $shortName
     * @param  string $className
     * @return PluginClassLocator
     */
    public function registerPlugin($shortName, $className);

    /**
     * Unregister a short name lookup
     *
     * @param  mixed $shortName
     * @return PluginClassLocator
     */
    public function unregisterPlugin($shortName);

    /**
     * Get a list of all registered plugins
     *
     * @return array|Traversable
     */
    public function getRegisteredPlugins();
}
