<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Loader;

// Grab SplAutoloader interface
require_once __DIR__ . '/SplAutoloader.php';

use GlobIterator;
use InvalidArgumentException;
use Jield\Stdlib\Value;
use Phar;
use PharFileInfo;
use SplFileInfo;
use Traversable;
use Webmozart\Assert\Assert;

use function array_map;
use function class_exists;
use function count;
use function extension_loaded;
use function getcwd;
use function gettype;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function pathinfo;
use function preg_match;
use function realpath;
use function rtrim;
use function spl_autoload_register;
use function spl_autoload_unregister;
use function sprintf;
use function str_replace;
use function substr;

use const DIRECTORY_SEPARATOR;

// Preserve the existing public API and the state and operations that implement it.
// @mago-expect lint:cyclomatic-complexity(1)
// @mago-expect lint:kan-defect(1)
// @mago-expect lint:too-many-methods(1)
class ModuleAutoloader implements SplAutoloader
{
    /** @var array An array of module paths to scan */
    protected $paths = [];

    /** @var array An array of modulename => path */
    protected $explicitPaths = [];

    /** @var array An array of namespaceName => namespacePath */
    protected $namespacedPaths = [];

    /** @var string Will contain the absolute phar:// path to the executable when packaged as phar file */
    protected $pharBasePath = '';

    /** @var array An array of supported phar extensions (filled on constructor) */
    protected $pharExtensions = [];

    /** @var array An array of module classes to their containing files */
    protected $moduleClassMap = [];

    /**
     * Allow configuration of the autoloader via the constructor.
     *
     * @param  null|array|Traversable $options
     */
    public function __construct($options = null)
    {
        if (extension_loaded('phar')) {
            $this->pharBasePath = Phar::running(true);
            $this->pharExtensions = [
                'phar',
                'phar.tar',
                'tar',
            ];

            // ext/zlib enabled -> phar can read gzip & zip compressed files
            if (extension_loaded('zlib')) {
                $this->pharExtensions[] = 'phar.gz';
                $this->pharExtensions[] = 'phar.tar.gz';
                $this->pharExtensions[] = 'tar.gz';

                $this->pharExtensions[] = 'phar.zip';
                $this->pharExtensions[] = 'zip';
            }

            // ext/bzip2 enabled -> phar can read bz2 compressed files
            if (extension_loaded('bzip2')) {
                $this->pharExtensions[] = 'phar.bz2';
                $this->pharExtensions[] = 'phar.tar.bz2';
                $this->pharExtensions[] = 'tar.bz2';
            }
        }

        if (null !== $options) {
            $this->setOptions($options);
        }
    }

    /**
     * Configure the autoloader
     *
     * In most cases, $options should be either an associative array or
     * Traversable object.
     *
     * @param  array|Traversable $options
     * @return ModuleAutoloader
     */
    public function setOptions($options)
    {
        $this->registerPaths($options);
        return $this;
    }

    /**
     * Retrieves the class map for all loaded modules.
     *
     * @return array
     */
    public function getModuleClassMap()
    {
        return $this->moduleClassMap;
    }

    /**
     * Sets the class map used to speed up the module autoloading.
     *
     * @return ModuleAutoloader
     */
    public function setModuleClassMap(array $classmap)
    {
        $this->moduleClassMap = $classmap;

        return $this;
    }

    /**
     * Autoload a class
     *
     * @param   string $class
     * @return  mixed
     *          False [if unable to load $class]
     *          get_class($class) [if $class is successfully loaded]
     * @throws \UnexpectedValueException
     */
    // Keep the coordinated parsing or rendering steps of this compatibility method together.
    // @mago-expect lint:halstead(1)
    public function autoload($class)
    {
        // Limit scope of this autoloader
        if (!str_ends_with($class, '\Module')) {
            return false;
        }

        if (($this->moduleClassMap[$class] ?? null) !== null) {
            require_once Assert::string($this->moduleClassMap[$class]);
            return $class;
        }

        $moduleName = substr($class, 0, -7);
        if (($this->explicitPaths[$moduleName] ?? null) !== null) {
            /** @mago-var mixed $classLoaded */
            $classLoaded = $this->loadModuleFromDir(Assert::string($this->explicitPaths[$moduleName]), $class);
            if ($classLoaded) {
                return $classLoaded;
            }

            /** @mago-var mixed $classLoaded */
            $classLoaded = $this->loadModuleFromPhar(Assert::string($this->explicitPaths[$moduleName]), $class);
            if ($classLoaded) {
                return $classLoaded;
            }
        }

        if (count($this->namespacedPaths) >= 1) {
            /** @mago-var mixed $path */
            foreach ($this->namespacedPaths as $namespace => $path) {
                if (!str_contains($moduleName, Assert::string($namespace))) {
                    continue;
                }

                $moduleNameBuffer = str_replace($namespace . "\\", '', $moduleName);
                $path = Value::stringify($path) . (DIRECTORY_SEPARATOR . $moduleNameBuffer . DIRECTORY_SEPARATOR);

                /** @mago-var mixed $classLoaded */
                $classLoaded = $this->loadModuleFromDir($path, $class);
                if ($classLoaded) {
                    return $classLoaded;
                }

                /** @mago-var mixed $classLoaded */
                $classLoaded = $this->loadModuleFromPhar($path, $class);
                if ($classLoaded) {
                    return $classLoaded;
                }
            }
        }

        $moduleClassPath = str_replace('\\', DIRECTORY_SEPARATOR, $moduleName);

        $pharSuffixPattern = null;
        if ($this->pharExtensions) {
            $pharSuffixPattern = '(' . implode('|', array_map(preg_quote(...), $this->pharExtensions)) . ')';
        }

        /** @mago-var mixed $path */
        foreach ($this->paths as $path) {
            $path = Value::stringify($path) . $moduleClassPath;

            if ($path === '.' || str_starts_with($path, './') || str_starts_with($path, '.\\')) {
                $basePath = $this->pharBasePath;
                if (!$basePath) {
                    $basePath = realpath('.');
                }

                if (false === $basePath) {
                    $basePath = getcwd();
                }

                $path = rtrim(Assert::string($basePath), '\/\\') . substr($path, 1);
            }

            /** @mago-var mixed $classLoaded */
            $classLoaded = $this->loadModuleFromDir($path, $class);
            if ($classLoaded) {
                return $classLoaded;
            }

            // No directory with Module.php, searching for phars
            if ($pharSuffixPattern) {
                foreach (new GlobIterator($path . '.*', GlobIterator::CURRENT_AS_FILEINFO) as $entry) {
                    $entry = new SplFileInfo((string) $entry);
                    if ($entry->isDir()) {
                        continue;
                    }

                    if (!preg_match('#.+\.' . $pharSuffixPattern . '$#', $entry->getPathname())) {
                        continue;
                    }

                    /** @mago-var mixed $classLoaded */
                    $classLoaded = $this->loadModuleFromPhar($entry->getPathname(), $class);
                    if ($classLoaded) {
                        return $classLoaded;
                    }
                }
            }
        }

        return false;
    }

    /**
     * loadModuleFromDir
     *
     * @param  string $dirPath
     * @param  string $class
     * @return  mixed
     *          False [if unable to load $class]
     *          get_class($class) [if $class is successfully loaded]
     * @throws \UnexpectedValueException
     */
    protected function loadModuleFromDir($dirPath, $class)
    {
        $modulePath = $dirPath . '/Module.php';
        $file = str_starts_with($modulePath, 'phar://') ? new PharFileInfo($modulePath) : new SplFileInfo($modulePath);

        if ($file->isReadable() && $file->isFile()) {
            // Found directory with Module.php in it
            $absModulePath = $this->pharBasePath ? (string) $file : $file->getRealPath();
            if ($absModulePath === false) {
                return false;
            }
            require_once $absModulePath;
            if (class_exists($class)) {
                $this->moduleClassMap[$class] = $absModulePath;
                return $class;
            }
        }
        return false;
    }

    /**
     * loadModuleFromPhar
     *
     * @param  string $pharPath
     * @param  string $class
     * @return  mixed
     *          False [if unable to load $class]
     *          get_class($class) [if $class is successfully loaded]
     */
    protected function loadModuleFromPhar($pharPath, $class)
    {
        $pharPath = static::normalizePath($pharPath, false);
        $file = new SplFileInfo($pharPath);
        if (!$file->isReadable() || !$file->isFile()) {
            return false;
        }

        $fileRealPath = $file->getRealPath();

        // Phase 0: Check for executable phar with Module class in stub
        if (str_contains(Assert::string($fileRealPath), '.phar')) {
            // First see if the stub makes the Module class available
            require_once $fileRealPath;
            if (class_exists($class)) {
                $this->moduleClassMap[$class] = $fileRealPath;
                return $class;
            }
        }

        // Phase 1: Not executable phar, no stub, or stub did not provide Module class; try Module.php directly
        $moduleClassFile = 'phar://' . $fileRealPath . '/Module.php';
        $moduleFile = new SplFileInfo($moduleClassFile);
        if ($moduleFile->isReadable() && $moduleFile->isFile()) {
            require_once $moduleClassFile;
            if (class_exists($class)) {
                $this->moduleClassMap[$class] = $moduleClassFile;
                return $class;
            }
        }

        // Phase 2: Check for nested module directory within archive
        // Checks for /path/to/MyModule.tar/MyModule/Module.php
        // (shell-integrated zip/tar utilities wrap directories like this)
        $pharBaseName = $this->pharFileToModuleName(Assert::string($fileRealPath));
        $moduleClassFile = 'phar://' . $fileRealPath . '/' . $pharBaseName . '/Module.php';
        $moduleFile = new SplFileInfo($moduleClassFile);
        if ($moduleFile->isReadable() && $moduleFile->isFile()) {
            require_once $moduleClassFile;
            if (class_exists($class)) {
                $this->moduleClassMap[$class] = $moduleClassFile;
                return $class;
            }
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
        spl_autoload_register($this->autoload(...));
    }

    /**
     * Unregister the autoloader with spl_autoload registry
     *
     * @return void
     * @throws \UnexpectedValueException
     */
    public function unregister()
    {
        spl_autoload_unregister($this->autoload(...));
    }

    /**
     * registerPaths
     *
     * @param  mixed $paths
     * @throws InvalidArgumentException
     * @return ModuleAutoloader
     */
    public function registerPaths($paths)
    {
        if (!is_array($paths) && !$paths instanceof Traversable) {
            require_once __DIR__ . '/Exception/InvalidArgumentException.php';
            throw new Exception\InvalidArgumentException(
                'Parameter to \\Laminas\\Loader\\ModuleAutoloader\'s '
                . 'registerPaths method must be an array or '
                . 'implement the Traversable interface',
            );
        }

        /**
         * @mago-var mixed $module
         * @mago-var mixed $path
         */
        foreach ($paths as $module => $path) {
            if (is_string($module)) {
                $this->registerPath(Assert::string($path), $module);
                continue;
            }
            $this->registerPath(Assert::string($path));
        }

        return $this;
    }

    /**
     * registerPath
     *
     * @param mixed $path
     * @param  bool|string $moduleName
     * @throws InvalidArgumentException
     * @return ModuleAutoloader
     */
    public function registerPath($path, $moduleName = false)
    {
        if (!is_string($path)) {
            require_once __DIR__ . '/Exception/InvalidArgumentException.php';
            throw new Exception\InvalidArgumentException(sprintf(
                'Invalid path provided; must be a string, received %s',
                gettype($path),
            ));
        }
        if ($moduleName) {
            // These alternatives are mutually exclusive; the following code consumes the selected state.
            // @mago-expect lint:no-else-clause(1)
            if (in_array(substr(Assert::string($moduleName), -2), ['\\*', '\\%'], strict: true)) {
                $this->namespacedPaths[substr(Assert::string($moduleName), 0, -2)] = static::normalizePath($path);
            } else {
                $this->explicitPaths[$moduleName] = static::normalizePath($path);
            }
            return $this;
        }
        $this->paths[] = static::normalizePath($path);
        return $this;
    }

    /**
     * getPaths
     *
     * This is primarily for unit testing, but could have other uses.
     *
     * @return array
     */
    public function getPaths()
    {
        return $this->paths;
    }

    /**
     * Returns the base module name from the path to a phar
     *
     * @param  string $pharPath
     * @return string
     */
    protected function pharFileToModuleName($pharPath)
    {
        do {
            $pathinfo = pathinfo($pharPath);
            $pharPath = $pathinfo['filename'];
        } while (($pathinfo['extension'] ?? null) !== null);
        return $pathinfo['filename'];
    }

    /**
     * Normalize a path for insertion in the stack
     *
     * @param mixed $path
     * @param  bool   $trailingSlash Whether trailing slash should be included
     * @return string
     */
    public static function normalizePath($path, $trailingSlash = true)
    {
        $path = rtrim(Assert::string($path), '/');
        $path = rtrim($path, '\\');
        if ($trailingSlash) {
            $path .= DIRECTORY_SEPARATOR;
        }
        return $path;
    }
}
