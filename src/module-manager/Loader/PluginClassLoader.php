<?php

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.Namespaces.UnusedUses.UnusedUse

namespace Jield\ModuleManager\Loader;

use ArrayIterator;
use IteratorAggregate;
use Jield\Stdlib\Value;
use ReturnTypeWillChange;
use Traversable;
use Webmozart\Assert\Assert;

use function array_key_exists;
use function class_exists;
use function is_array;
use function is_int;
use function is_numeric;
use function is_object;
use function is_string;
use function strtolower;

/**
 * Plugin class locator interface
 */
class PluginClassLoader implements PluginClassLocator
{
    /**
     * List of plugin name => class name pairs
     *
     * @var array
     */
    protected $plugins = [];

    /**
     * Static map allow global seeding of plugin loader
     *
     * @var array
     */
    protected static $staticMap = [];

    /**
     * Constructor
     *
     * @param  null|array|Traversable $map If provided, seeds the loader with a map
     */
    public function __construct($map = null)
    {
        // Merge in static overrides
        if (!empty(static::$staticMap)) {
            $this->registerPlugins(static::$staticMap);
        }

        // Merge in constructor arguments
        if ($map !== null) {
            $this->registerPlugins($map);
        }
    }

    /**
     * Add a static map of plugins
     *
     * A null value will clear the static map.
     *
     * @param  mixed $map
     * @throws Exception\InvalidArgumentException
     * @return void
     */
    public static function addStaticMap($map)
    {
        if (null === $map) {
            static::$staticMap = [];
            return;
        }

        if (!is_array($map) && !$map instanceof Traversable) {
            throw new Exception\InvalidArgumentException('Expects an array or Traversable object');
        }
        /**
         * @mago-var mixed $key
         * @mago-var mixed $value
         */
        foreach ($map as $key => $value) {
            static::$staticMap[Value::key($key)] = $value;
        }
    }

    /**
     * Register a class to a given short name
     *
     * @param  string $shortName
     * @param  string $className
     * @return PluginClassLoader
     */
    public function registerPlugin($shortName, $className)
    {
        $this->plugins[strtolower($shortName)] = $className;
        return $this;
    }

    /**
     * Register many plugins at once
     *
     * If $map is a string, assumes that the map is the class name of a
     * Traversable object (likely a ShortNameLocator); it will then instantiate
     * this class and use it to register plugins.
     *
     * If $map is an array or Traversable object, it will iterate it to
     * register plugin names/classes.
     *
     * For all other arguments, or if the string $map is not a class or not a
     * Traversable class, an exception will be raised.
     *
     * @param  mixed $map
     * @return PluginClassLoader
     * @throws Exception\InvalidArgumentException
     */
    public function registerPlugins($map)
    {
        if (is_string($map)) {
            if (!class_exists($map)) {
                throw new Exception\InvalidArgumentException('Map class provided is invalid');
            }
            $map = Value::instantiate($map);
        }
        if (is_array($map)) {
            $map = new ArrayIterator($map);
        }
        if (!$map instanceof Traversable) {
            throw new Exception\InvalidArgumentException('Map provided is invalid; must be traversable');
        }

        // iterator_apply doesn't work as expected with IteratorAggregate
        if ($map instanceof IteratorAggregate) {
            $map = $map->getIterator();
        }

        /**
         * @mago-var mixed $name
         * @mago-var mixed $class
         */
        foreach ($map as $name => $class) {
            if (is_int($name) || is_numeric($name)) {
                if (!is_object($class) && class_exists(Assert::string($class))) {
                    $class = Value::instantiate($class);
                }

                if ($class instanceof Traversable) {
                    $this->registerPlugins($class);
                    continue;
                }
            }

            $this->registerPlugin(Assert::string($name), Assert::string($class));
        }

        return $this;
    }

    /**
     * Unregister a short name lookup
     *
     * @param  mixed $shortName
     * @return PluginClassLoader
     */
    public function unregisterPlugin($shortName)
    {
        $lookup = strtolower(Assert::string($shortName));
        if (array_key_exists($lookup, $this->plugins)) {
            unset($this->plugins[$lookup]);
        }
        return $this;
    }

    /**
     * Get a list of all registered plugins
     *
     * @return array|Traversable
     */
    public function getRegisteredPlugins()
    {
        return $this->plugins;
    }

    /**
     * Whether or not a plugin by a specific name has been registered
     *
     * @param  string $name
     * @return bool
     */
    public function isLoaded($name)
    {
        $lookup = strtolower($name);
        return isset($this->plugins[$lookup]);
    }

    /**
     * Return full class name for a named helper
     *
     * @param  string $name
     * @return string|false
     */
    public function getClassName($name)
    {
        return $this->load($name);
    }

    /**
     * Load a helper via the name provided
     *
     * @param  string $name
     * @return string|false
     */
    public function load($name)
    {
        if (!$this->isLoaded($name)) {
            return false;
        }
        return Assert::string($this->plugins[strtolower($name)]);
    }

    /**
     * Defined by IteratorAggregate
     *
     * Returns an instance of ArrayIterator, containing a map of
     * all plugins
     *
     * @return ArrayIterator
     */
    #[ReturnTypeWillChange]
    public function getIterator()
    {
        return new ArrayIterator($this->plugins);
    }
}
