<?php

namespace Jield\Mvc\Loader;

use Traversable;
use function class_exists;
use function is_array;
use function is_subclass_of;
use function spl_autoload_unregister;
use function sprintf;
use function strrchr;
use function substr;

abstract class AutoloaderFactory
{
    public const string STANDARD_AUTOLOADER = ModuleAutoloader::class;

    /** @var array All autoloaders registered using the factory */
    protected static array $loaders = [];

    /**
     * @var ModuleAutoloader ModuleAutoloader instance for resolving
     * autoloader classes via the include_path
     */
    protected static ModuleAutoloader $moduleAutoloader;

    /**
     * Factory for autoloaders
     *
     * Options should be an array or Traversable object of the following structure:
     * <code>
     * array(
     *     '<autoloader class name>' => $autoloaderOptions,
     * )
     * </code>
     *
     * The factory will then loop through and instantiate each autoloader with
     * the specified options, and register each with the spl_autoloader.
     *
     * You may retrieve the concrete autoloader instances later using
     * {@link getRegisteredAutoloaders()}.
     *
     * Note that the class names must be resolvable on the include_path or via
     * the Laminas library, using PSR-0 rules (unless the class has already been
     * loaded).
     *
     * @param Traversable|array|null $options (optional) options to use. Defaults to Laminas\Loader\ModuleAutoloader
     * @return void
     * @throws \InvalidArgumentException For invalid options.
     * @throws \InvalidArgumentException For unloadable autoloader classes.
     * @throws \DomainException For autoloader classes not implementing SplAutoloader.
     */
    public static function factory(iterable|null $options = null)
    {
        if (null === $options) {
            if (!isset(static::$loaders[static::STANDARD_AUTOLOADER])) {
                $autoloader = static::getModuleAutoloader();
                $autoloader->register();
                static::$loaders[static::STANDARD_AUTOLOADER] = $autoloader;
            }

            // Return so we don't hit the next check's exception (we're done here anyway)
            return;
        }

        if (!is_array($options) && !$options instanceof Traversable) {
            throw new \InvalidArgumentException(
                'Options provided must be an array or Traversable'
            );
        }

        foreach ($options as $class => $autoloaderOptions) {
            if (!isset(static::$loaders[$class])) {
                $autoloader = static::getModuleAutoloader();
                if (!class_exists($class) && !$autoloader->autoload($class)) {
                    throw new \InvalidArgumentException(
                        sprintf('Autoloader class "%s" not loaded', $class)
                    );
                }

                if (!is_subclass_of(object_or_class: $class, class: SplAutoloader::class)) {
                    throw new \InvalidArgumentException(
                        sprintf('Autoloader class %s must implement Jield\\Mvc\\Loader\\SplAutoloader', $class)
                    );
                }

                if ($class === static::STANDARD_AUTOLOADER) {
                    $autoloader->setOptions($autoloaderOptions);
                } else {
                    $autoloader = new $class($autoloaderOptions);
                }
                $autoloader->register();
                static::$loaders[$class] = $autoloader;
            } else {
                static::$loaders[$class]->setOptions($autoloaderOptions);
            }
        }
    }

    /**
     * Get a list of all autoloaders registered with the factory
     *
     * Returns an array of autoloader instances.
     *
     * @return array
     */
    public static function getRegisteredAutoloaders()
    {
        return static::$loaders;
    }

    /**
     * Retrieves an autoloader by class name
     *
     * @param string $class
     * @return SplAutoloader
     * @throws \InvalidArgumentException For non-registered class.
     */
    public static function getRegisteredAutoloader($class)
    {
        if (!isset(static::$loaders[$class])) {
            throw new \InvalidArgumentException(sprintf('Autoloader class "%s" not loaded', $class));
        }
        return static::$loaders[$class];
    }

    /**
     * Unregisters all autoloaders that have been registered via the factory.
     * This will NOT unregister autoloaders registered outside of the fctory.
     *
     * @return void
     */
    public static function unregisterAutoloaders()
    {
        foreach (static::getRegisteredAutoloaders() as $class => $autoloader) {
            spl_autoload_unregister([$autoloader, 'autoload']);
            unset(static::$loaders[$class]);
        }
    }

    /**
     * Unregister a single autoloader by class name
     *
     * @param string $autoloaderClass
     * @return bool
     */
    public static function unregisterAutoloader($autoloaderClass)
    {
        if (!isset(static::$loaders[$autoloaderClass])) {
            return false;
        }

        $autoloader = static::$loaders[$autoloaderClass];
        spl_autoload_unregister([$autoloader, 'autoload']);
        unset(static::$loaders[$autoloaderClass]);
        return true;
    }

    /**
     * Get an instance of the standard autoloader
     *
     * Used to attempt to resolve autoloader classes, using the
     * ModuleAutoloader. The instance is marked as a fallback autoloader, to
     * allow resolving autoloaders not under the "Laminas" namespace.
     *
     * @return SplAutoloader
     */
    protected static function getModuleAutoloader(): ModuleAutoloader|SplAutoloader
    {
        if (null !== static::$moduleAutoloader) {
            return static::$moduleAutoloader;
        }

        if (!class_exists(static::STANDARD_AUTOLOADER)) {
            // Extract the filename from the classname
            $stdAutoloader = substr(string: strrchr(haystack: static::STANDARD_AUTOLOADER, needle: '\\'), offset: 1);
            require_once __DIR__ . "/$stdAutoloader.php";
        }
        $loader                   = new ModuleAutoloader();
        static::$moduleAutoloader = $loader;
        return static::$moduleAutoloader;
    }
}
