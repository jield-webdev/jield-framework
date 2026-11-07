<?php

declare(strict_types=1);

namespace Jield\Mvc\Loader;

use function interface_exists;

if (interface_exists(SplAutoloader::class)) {
    return;
}

/**
 * Defines an interface for classes that may register with the spl_autoload
 * registry
 */
interface SplAutoloader
{
    /**
     * Allow configuration of the autoloader via the constructor.
     */
    public function __construct(?iterable $options = null);

    /**
     * Configure the autoloader
     *
     * In most cases, $options should be either an associative array or
     * Traversable object.
     *
     * @return SplAutoloader
     */
    public function setOptions(iterable $options);

    /**
     * Autoload a class
     *
     * @return  mixed
     *          False [if unable to load $class]
     *          get_class($class) [if $class is successfully loaded]
     */
    public function autoload(string $class): mixed;

    /**
     * Register the autoloader with spl_autoload registry
     *
     * Typically, the body of this will simply be:
     * <code>
     * spl_autoload_register(array($this, 'autoload'));
     * </code>
     */
    public function register(): void;
}
