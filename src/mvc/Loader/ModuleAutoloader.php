<?php

namespace Jield\Mvc\Loader;

use GlobIterator;
use InvalidArgumentException;
use Phar;
use PharFileInfo;
use SplFileInfo;
use Traversable;
use function array_map;
use function class_exists;
use function count;
use function extension_loaded;
use function getcwd;
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
use function str_replace;
use function strpos;
use function substr;
use const DIRECTORY_SEPARATOR;

class ModuleAutoloader implements SplAutoloader
{
    protected array $paths = [];

    protected array  $explicitPaths   = [];
    protected array  $namespacedPaths = [];
    protected string $pharBasePath    = "";
    protected array  $pharExtensions  = [];
    protected array  $moduleClassMap  = [];

    /**
     * Constructor
     *
     * Allow configuration of the autoloader via the constructor.
     *
     * @param iterable|null $options
     */
    public function __construct(?iterable $options = null)
    {
        if (extension_loaded(extension: 'phar')) {
            $this->pharBasePath   = Phar::running(returnPhar: true);
            $this->pharExtensions = [
                'phar',
                'phar.tar',
                'tar',
            ];

            // ext/zlib enabled -> phar can read gzip & zip compressed files
            if (extension_loaded(extension: 'zlib')) {
                $this->pharExtensions[] = 'phar.gz';
                $this->pharExtensions[] = 'phar.tar.gz';
                $this->pharExtensions[] = 'tar.gz';

                $this->pharExtensions[] = 'phar.zip';
                $this->pharExtensions[] = 'zip';
            }

            // ext/bzip2 enabled -> phar can read bz2 compressed files
            if (extension_loaded(extension: 'bzip2')) {
                $this->pharExtensions[] = 'phar.bz2';
                $this->pharExtensions[] = 'phar.tar.bz2';
                $this->pharExtensions[] = 'tar.bz2';
            }
        }

        if (null !== $options) {
            $this->setOptions(options: $options);
        }
    }

    /**
     * Configure the autoloader
     *
     * In most cases, $options should be either an associative array or
     * Traversable object.
     *
     * @param iterable $options
     * @return ModuleAutoloader
     */
    public function setOptions(iterable $options): static
    {
        $this->registerPaths(paths: $options);
        return $this;
    }

    /**
     * Retrieves the class map for all loaded modules.
     *
     * @return array
     */
    public function getModuleClassMap(): array
    {
        return $this->moduleClassMap;
    }

    /**
     * Sets the class map used to speed up the module autoloading.
     *
     * @param array $classmap
     * @return ModuleAutoloader
     */
    public function setModuleClassMap(array $classmap): static
    {
        $this->moduleClassMap = $classmap;

        return $this;
    }

    /**
     * Autoload a class
     *
     * @param string $class
     * @return  mixed
     *          False [if unable to load $class]
     *          get_class($class) [if $class is successfully loaded]
     */
    public function autoload(string $class): mixed
    {
        // Limit scope of this autoloader
        if (!str_ends_with($class, '\Module')) {
            return false;
        }

        if (isset($this->moduleClassMap[$class])) {
            require_once $this->moduleClassMap[$class];
            return $class;
        }

        $moduleName = substr(string: $class, offset: 0, length: -7);
        if (isset($this->explicitPaths[$moduleName])) {
            $classLoaded = $this->loadModuleFromDir(dirPath: $this->explicitPaths[$moduleName], class: $class);
            if ($classLoaded) {
                return $classLoaded;
            }

            $classLoaded = $this->loadModuleFromPhar(pharPath: $this->explicitPaths[$moduleName], class: $class);
            if ($classLoaded) {
                return $classLoaded;
            }
        }

        if (count(value: $this->namespacedPaths) >= 1) {
            foreach ($this->namespacedPaths as $namespace => $path) {
                if (false === strpos(haystack: $moduleName, needle: $namespace)) {
                    continue;
                }

                $moduleNameBuffer = str_replace(search: $namespace . "\\", replace: "", subject: $moduleName);
                $path             .= DIRECTORY_SEPARATOR . $moduleNameBuffer . DIRECTORY_SEPARATOR;

                $classLoaded = $this->loadModuleFromDir(dirPath: $path, class: $class);
                if ($classLoaded) {
                    return $classLoaded;
                }

                $classLoaded = $this->loadModuleFromPhar(pharPath: $path, class: $class);
                if ($classLoaded) {
                    return $classLoaded;
                }
            }
        }

        $moduleClassPath = str_replace(search: '\\', replace: DIRECTORY_SEPARATOR, subject: $moduleName);

        $pharSuffixPattern = null;
        if ($this->pharExtensions) {
            $pharSuffixPattern = '(' . implode(separator: '|', array: array_map(callback: 'preg_quote', array: $this->pharExtensions)) . ')';
        }

        foreach ($this->paths as $path) {
            $path .= $moduleClassPath;

            if ($path === '.' || str_starts_with($path, './') || str_starts_with($path, '.\\')) {
                if (!$basePath = $this->pharBasePath) {
                    $basePath = realpath(path: '.');
                }

                if (false === $basePath) {
                    $basePath = getcwd();
                }

                $path = rtrim(string: $basePath, characters: '\/\\') . substr(string: $path, offset: 1);
            }

            $classLoaded = $this->loadModuleFromDir(dirPath: $path, class: $class);
            if ($classLoaded) {
                return $classLoaded;
            }

            // No directory with Module.php, searching for phars
            if ($pharSuffixPattern) {
                foreach (new GlobIterator(pattern: $path . '.*') as $entry) {
                    if ($entry->isDir()) {
                        continue;
                    }

                    if (!preg_match(pattern: '#.+\.' . $pharSuffixPattern . '$#', subject: $entry->getPathname())) {
                        continue;
                    }

                    $classLoaded = $this->loadModuleFromPhar(pharPath: $entry->getPathname(), class: $class);
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
     * @param string $dirPath
     * @param string $class
     * @return  mixed
     *          False [if unable to load $class]
     *          get_class($class) [if $class is successfully loaded]
     */
    protected function loadModuleFromDir(string $dirPath, string $class): mixed
    {
        $modulePath = $dirPath . '/Module.php';
        if (str_starts_with($modulePath, 'phar://')) {
            $file = new PharFileInfo(filename: $modulePath);
        } else {
            $file = new SplFileInfo(filename: $modulePath);
        }

        if ($file->isReadable() && $file->isFile()) {
            // Found directory with Module.php in it
            $absModulePath = $this->pharBasePath ? (string)$file : $file->getRealPath();
            require_once $absModulePath;
            if (class_exists(class: $class)) {
                $this->moduleClassMap[$class] = $absModulePath;
                return $class;
            }
        }
        return false;
    }

    /**
     * loadModuleFromPhar
     *
     * @param string $pharPath
     * @param string $class
     * @return  mixed
     *          False [if unable to load $class]
     *          get_class($class) [if $class is successfully loaded]
     */
    protected function loadModuleFromPhar(string $pharPath, string $class): mixed
    {
        $pharPath = static::normalizePath(path: $pharPath, trailingSlash: false);
        $file     = new SplFileInfo(filename: $pharPath);
        if (!$file->isReadable() || !$file->isFile()) {
            return false;
        }

        $fileRealPath = $file->getRealPath();

        // Phase 0: Check for executable phar with Module class in stub
        if (str_contains($fileRealPath, '.phar')) {
            // First see if the stub makes the Module class available
            require_once $fileRealPath;
            if (class_exists(class: $class)) {
                $this->moduleClassMap[$class] = $fileRealPath;
                return $class;
            }
        }

        // Phase 1: Not executable phar, no stub, or stub did not provide Module class; try Module.php directly
        $moduleClassFile = 'phar://' . $fileRealPath . '/Module.php';
        $moduleFile      = new SplFileInfo(filename: $moduleClassFile);
        if ($moduleFile->isReadable() && $moduleFile->isFile()) {
            require_once $moduleClassFile;
            if (class_exists(class: $class)) {
                $this->moduleClassMap[$class] = $moduleClassFile;
                return $class;
            }
        }

        // Phase 2: Check for nested module directory within archive
        // Checks for /path/to/MyModule.tar/MyModule/Module.php
        // (shell-integrated zip/tar utilities wrap directories like this)
        $pharBaseName    = $this->pharFileToModuleName(pharPath: $fileRealPath);
        $moduleClassFile = 'phar://' . $fileRealPath . '/' . $pharBaseName . '/Module.php';
        $moduleFile      = new SplFileInfo(filename: $moduleClassFile);
        if ($moduleFile->isReadable() && $moduleFile->isFile()) {
            require_once $moduleClassFile;
            if (class_exists(class: $class)) {
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
    public function register(): void
    {
        spl_autoload_register(callback: [$this, 'autoload']);
    }

    /**
     * Unregister the autoloader with spl_autoload registry
     *
     * @return void
     */
    public function unregister(): void
    {
        spl_autoload_unregister(callback: [$this, 'autoload']);
    }

    /**
     * registerPaths
     *
     * @param Traversable|array $paths
     * @return ModuleAutoloader
     * @throws InvalidArgumentException
     */
    public function registerPaths(Traversable|array $paths): static
    {
        if (!is_array(value: $paths) && !$paths instanceof Traversable) {
            require_once __DIR__ . '/Exception/InvalidArgumentException.php';
            throw new \InvalidArgumentException(
                'Parameter to \\Laminas\\Loader\\ModuleAutoloader\'s '
                . 'registerPaths method must be an array or '
                . 'implement the Traversable interface'
            );
        }

        foreach ($paths as $module => $path) {
            if (is_string(value: $module)) {
                $this->registerPath(path: $path, moduleName: $module);
            } else {
                $this->registerPath(path: $path);
            }
        }

        return $this;
    }

    /**
     * registerPath
     *
     * @param string $path
     * @param bool|string $moduleName
     * @return ModuleAutoloader
     * @throws InvalidArgumentException
     */
    public function registerPath(string $path, bool|string $moduleName = false): static
    {
        if ($moduleName) {
            if (in_array(needle: substr(string: $moduleName, offset: -2), haystack: ['\\*', '\\%'])) {
                $this->namespacedPaths[substr(string: $moduleName, offset: 0, length: -2)] = static::normalizePath(path: $path);
            } else {
                $this->explicitPaths[$moduleName] = static::normalizePath(path: $path);
            }
        } else {
            $this->paths[] = static::normalizePath(path: $path);
        }
        return $this;
    }

    /**
     * getPaths
     *
     * This is primarily for unit testing, but could have other uses.
     *
     * @return array
     */
    public function getPaths(): array
    {
        return $this->paths;
    }

    /**
     * Returns the base module name from the path to a phar
     *
     * @param string $pharPath
     * @return string
     */
    protected function pharFileToModuleName(string $pharPath): string
    {
        do {
            $pathinfo = pathinfo(path: $pharPath);
            $pharPath = $pathinfo['filename'];
        } while (isset($pathinfo['extension']));
        return $pathinfo['filename'];
    }

    /**
     * Normalize a path for insertion in the stack
     *
     * @param string $path
     * @param bool $trailingSlash Whether trailing slash should be included
     * @return string
     */
    public static function normalizePath(string $path, bool $trailingSlash = true): string
    {
        $path = rtrim(string: $path, characters: '/');
        $path = rtrim(string: $path, characters: '\\');
        if ($trailingSlash) {
            $path .= DIRECTORY_SEPARATOR;
        }
        return $path;
    }
}
