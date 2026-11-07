<?php

declare(strict_types=1);

namespace Jield\Mvc\Loader;

use DomainException;
use InvalidArgumentException;
use Jield\Stdlib\Value;
use Traversable;
use Webmozart\Assert\Assert;

use function class_exists;
use function is_subclass_of;
use function spl_autoload_unregister;
use function sprintf;
use function strrchr;
use function substr;

abstract class AutoloaderFactory
{
    public const string STANDARD_AUTOLOADER = ModuleAutoloader::class;

    /** @var array<string, SplAutoloader> */
    protected static array $loaders = [];

    /**
     * Module autoloader used to resolve classes via the include_path, initialized on first use.
     */
    protected static ?ModuleAutoloader $moduleAutoloader = null;

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
     * @param iterable|null $options (optional) options to use. Defaults to Jield\Mvc\Loader\ModuleAutoloader
     * @return void
     * @throws InvalidArgumentException For invalid options.
     * @throws InvalidArgumentException For unloadable autoloader classes.
     * @throws DomainException For autoloader classes not implementing SplAutoloader.
     * @throws \ReflectionException
     */
    public static function factory(?iterable $options = null)
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

        /**
         * @mago-var mixed $class
         * @mago-var mixed $autoloaderOptions
         */
        foreach ($options as $class => $autoloaderOptions) {
            $class = Assert::string($class);
            if (!isset(static::$loaders[$class])) {
                $autoloader = static::getModuleAutoloader();
                if (!class_exists(Assert::string($class)) && !$autoloader->autoload(Assert::string($class))) {
                    throw new InvalidArgumentException(sprintf('Autoloader class "%s" not loaded', Value::scalar($class)));
                }

                if (!is_subclass_of(object_or_class: Assert::string($class), class: SplAutoloader::class)) {
                    throw new InvalidArgumentException(sprintf(
                        'Autoloader class %s must implement Jield\\ModuleManager\\Loader\\SplAutoloader',
                        Value::scalar($class),
                    ));
                }

                if ($class === static::STANDARD_AUTOLOADER) {
                    $autoloader->setOptions(Assert::isIterable($autoloaderOptions));
                } else {
                    $autoloader = Assert::isInstanceOf(
                        Value::instantiate($class, [$autoloaderOptions]),
                        SplAutoloader::class,
                    );
                }
                $autoloader->register();
                static::$loaders[$class] = $autoloader;
            } else {
                Value::call(static::$loaders[$class], 'setOptions', [$autoloaderOptions]);
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
     * @throws InvalidArgumentException For non-registered class.
     */
    public static function getRegisteredAutoloader($class)
    {
        if (!isset(static::$loaders[$class])) {
            throw new InvalidArgumentException(sprintf('Autoloader class "%s" not loaded', $class));
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
        /** @mago-var mixed $autoloader */
        foreach (static::getRegisteredAutoloaders() as $class => $autoloader) {
            spl_autoload_unregister(Assert::isCallable([$autoloader, 'autoload']));
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
            $stdAutoloader = substr(
                string: Assert::string(strrchr(haystack: static::STANDARD_AUTOLOADER, needle: '\\')),
                offset: 1,
            );
            require_once __DIR__ . "/{$stdAutoloader}.php";
        }
        $loader = new ModuleAutoloader();
        static::$moduleAutoloader = $loader;
        return static::$moduleAutoloader;
    }
}
