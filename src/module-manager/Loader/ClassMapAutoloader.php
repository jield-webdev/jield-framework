<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Loader;

use Jield\Stdlib\Value;
use Traversable;
use function array_filter;
use function array_values;
use function array_walk;
use function explode;
use function file_exists;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function preg_match;
use function realpath;
use function spl_autoload_register;
use function sprintf;
use function str_pad;
use function str_replace;
use function strlen;
use function substr;

// Grab SplAutoloader interface
require_once __DIR__ . '/SplAutoloader.php';

/**
 * Class-map autoloader
 *
 * Utilizes class-map files to lookup classfile locations.
 */
class ClassMapAutoloader implements SplAutoloader
{
    /**
     * Registry of map files that have already been loaded
     *
     * @var array
     */
    protected $mapsLoaded = [];

    /**
     * Class name/filename map
     *
     * @var array
     */
    protected $map = [];

    /**
     * Constructor
     *
     * Create a new instance, and optionally configure the autoloader.
     *
     * @param null|array|Traversable $options
     */
    public function __construct($options = null)
    {
        if (null !== $options) {
            $this->setOptions($options);
        }
    }

    /**
     * Configure the autoloader
     *
     * Proxies to {@link registerAutoloadMaps()}.
     *
     * @param array|Traversable $options
     * @return ClassMapAutoloader
     */
    public function setOptions($options)
    {
        $this->registerAutoloadMaps(Value::array($options));
        return $this;
    }

    /**
     * Register an autoload map
     *
     * An autoload map may be either an associative array, or a file returning
     * an associative array.
     *
     * An autoload map should be an associative array containing
     * classname/file pairs.
     *
     * @param string|array $map
     * @return ClassMapAutoloader
     * @throws Exception\InvalidArgumentException
     */
    public function registerAutoloadMap($map)
    {
        $location = null;
        if (is_string($map)) {
            $location = $map;
            /** @var mixed $map */
            if ($this === ($map = $this->loadMapFromFile($location))) {
                return $this;
            }
        }

        if (!is_array($map)) {
            require_once __DIR__ . '/Exception/InvalidArgumentException.php';
            throw new Exception\InvalidArgumentException(sprintf(
                'Map file provided does not return a map. Map file: "%s"',
                $location,
            ));
        }

        $this->map = $map + $this->map;

        if (isset($location)) {
            $this->mapsLoaded[] = $location;
        }

        return $this;
    }

    /**
     * Register many autoload maps at once
     *
     * @param mixed $locations
     * @return ClassMapAutoloader
     * @throws Exception\InvalidArgumentException
     */
    public function registerAutoloadMaps($locations)
    {
        if (!is_array($locations) && !$locations instanceof Traversable) {
            require_once __DIR__ . '/Exception/InvalidArgumentException.php';
            throw new Exception\InvalidArgumentException('Map list must be an array or implement Traversable');
        }
        /** @var mixed $location */
        foreach ($locations as $location) {
            $this->registerAutoloadMap(Value::arrayOrString($location));
        }
        return $this;
    }

    /**
     * Retrieve current autoload map
     *
     * @return array
     */
    public function getAutoloadMap()
    {
        return $this->map;
    }

    /**
     * {@inheritDoc}
     */
    public function autoload($class)
    {
        if (isset($this->map[$class])) {
            require_once Value::string($this->map[$class]);

            return $class;
        }

        return false;
    }

    /**
     * Register the autoloader with spl_autoload registry
     *
     * @return void
     */
    public function register()
    {
        spl_autoload_register($this->autoload(...), true, true);
    }

    /**
     * Load a map from a file
     *
     * If the map has been previously loaded, returns the current instance;
     * otherwise, returns whatever was returned by calling include() on the
     * location.
     *
     * @param string $location
     * @return ClassMapAutoloader|mixed
     * @throws Exception\InvalidArgumentException For nonexistent locations.
     */
    protected function loadMapFromFile($location)
    {
        if (!file_exists($location)) {
            require_once __DIR__ . '/Exception/InvalidArgumentException.php';
            throw new Exception\InvalidArgumentException(sprintf(
                'Map file provided does not exist. Map file: "%s"',
                $location,
            ));
        }

        if (!($path = static::realPharPath($location))) {
            $path = realpath($location);
        }

        if (in_array($path, $this->mapsLoaded, strict: true)) {
            // Already loaded this map
            return $this;
        }

        if ($path === false) {
            throw new Exception\InvalidArgumentException(sprintf('Cannot resolve map file "%s".', $location));
        }
        return include $path;
    }

    /**
     * Resolve the real_path() to a file within a phar.
     *
     * @see https://bugs.php.net/bug.php?id=52769
     *
     * @param string $path
     * @return string|null
     */
    public static function realPharPath($path)
    {
        $match = [];
        if (!preg_match('|^phar:(/{2,3})|', $path, $match)) {
            return null;
        }

        $prefixLength = 5 + strlen($match[1]);
        $parts        = explode('/', str_replace(['/', '\\'], '/', substr($path, $prefixLength)));
        $parts        = array_values(array_filter($parts, static fn($p) => $p !== '' && $p !== '.'));

        array_walk($parts, static function ($value, $key) use (&$parts): void {
            if ($value === '..') {
                unset($parts[$key], $parts[$key - 1]);
                $parts = array_values($parts);
            }
        });

        if (file_exists(
            $realPath = str_pad('phar:', $prefixLength, '/') . implode('/', Value::scalars($parts)),
        )) {
            return $realPath;
        }
        return null;
    }
}
