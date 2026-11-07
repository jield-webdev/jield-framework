<?php

declare(strict_types=1);

namespace Jield\Mvc\Loader;

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
use function implode;
use function in_array;
use function is_string;
use function pathinfo;
use function preg_match;
use function realpath;
use function rtrim;
use function spl_autoload_register;
use function spl_autoload_unregister;
use function str_replace;
use function substr;

use const DIRECTORY_SEPARATOR;

// Preserve the existing public API and the state and operations that implement it.
// @mago-expect lint:cyclomatic-complexity(1)
// @mago-expect lint:kan-defect(1)
// @mago-expect lint:too-many-methods(1)
class ModuleAutoloader implements SplAutoloader
{
    protected array $paths = [];

    protected array $explicitPaths = [];
    protected array $namespacedPaths = [];
    protected string $pharBasePath = '';
    protected array $pharExtensions = [];
    protected array $moduleClassMap = [];

    /**
     * Allow configuration of the autoloader via the constructor.
     */
    public function __construct(?iterable $options = null)
    {
        if (extension_loaded(extension: 'phar')) {
            $this->pharBasePath = Phar::running(returnPhar: true);
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
     */
    public function setOptions(iterable $options): static
    {
        $this->registerPaths(paths: $options);
        return $this;
    }

    /**
     * Retrieves the class map for all loaded modules.
     */
    public function getModuleClassMap(): array
    {
        return $this->moduleClassMap;
    }

    /**
     * Sets the class map used to speed up the module autoloading.
     */
    public function setModuleClassMap(array $classmap): static
    {
        $this->moduleClassMap = $classmap;

        return $this;
    }

    /**
     * Autoload a class
     *
     * @return  mixed
     *          False [if unable to load $class]
     *          get_class($class) [if $class is successfully loaded]
     * @throws \UnexpectedValueException
     */
    // Keep the coordinated parsing or rendering steps of this compatibility method together.
    // @mago-expect lint:halstead(1)
    public function autoload(string $class): mixed
    {
        // Limit scope of this autoloader
        if (!str_ends_with($class, '\Module')) {
            return false;
        }

        if (($this->moduleClassMap[$class] ?? null) !== null) {
            require_once Assert::string($this->moduleClassMap[$class]);
            return $class;
        }

        $moduleName = substr(string: $class, offset: 0, length: -7);
        if (($this->explicitPaths[$moduleName] ?? null) !== null) {
            /** @mago-var mixed $classLoaded */
            $classLoaded = $this->loadModuleFromDir(
                dirPath: Assert::string($this->explicitPaths[$moduleName]),
                class: $class,
            );
            if ($classLoaded) {
                return $classLoaded;
            }

            /** @mago-var mixed $classLoaded */
            $classLoaded = $this->loadModuleFromPhar(
                pharPath: Assert::string($this->explicitPaths[$moduleName]),
                class: $class,
            );
            if ($classLoaded) {
                return $classLoaded;
            }
        }

        if (count(value: $this->namespacedPaths) >= 1) {
            /** @mago-var mixed $path */
            foreach ($this->namespacedPaths as $namespace => $path) {
                if (!str_contains(haystack: $moduleName, needle: Assert::string($namespace))) {
                    continue;
                }

                $moduleNameBuffer = str_replace(search: $namespace . "\\", replace: '', subject: $moduleName);
                $path = Value::stringify($path) . (DIRECTORY_SEPARATOR . $moduleNameBuffer . DIRECTORY_SEPARATOR);

                /** @mago-var mixed $classLoaded */
                $classLoaded = $this->loadModuleFromDir(dirPath: $path, class: $class);
                if ($classLoaded) {
                    return $classLoaded;
                }

                /** @mago-var mixed $classLoaded */
                $classLoaded = $this->loadModuleFromPhar(pharPath: $path, class: $class);
                if ($classLoaded) {
                    return $classLoaded;
                }
            }
        }

        $moduleClassPath = str_replace(search: '\\', replace: DIRECTORY_SEPARATOR, subject: $moduleName);

        $pharSuffixPattern = null;
        if ($this->pharExtensions) {
            $pharSuffixPattern =
                '('
                . implode(separator: '|', array: array_map(callback: preg_quote(...), array: $this->pharExtensions))
                . ')';
        }

        /** @mago-var mixed $path */
        foreach ($this->paths as $path) {
            $path = Value::stringify($path) . $moduleClassPath;

            if ($path === '.' || str_starts_with($path, './') || str_starts_with($path, '.\\')) {
                $basePath = $this->pharBasePath;
                if (!$basePath) {
                    $basePath = realpath(path: '.');
                }

                if (false === $basePath) {
                    $basePath = getcwd();
                }

                $path = rtrim(string: Assert::string($basePath), characters: '\/\\') . substr(string: $path, offset: 1);
            }

            /** @mago-var mixed $classLoaded */
            $classLoaded = $this->loadModuleFromDir(dirPath: $path, class: $class);
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

                    if (!preg_match(pattern: '#.+\.' . $pharSuffixPattern . '$#', subject: $entry->getPathname())) {
                        continue;
                    }

                    /** @mago-var mixed $classLoaded */
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
     * @return  mixed
     *          False [if unable to load $class]
     *          get_class($class) [if $class is successfully loaded]
     * @throws \UnexpectedValueException
     */
    protected function loadModuleFromDir(string $dirPath, string $class): mixed
    {
        $modulePath = $dirPath . '/Module.php';
        $file = str_starts_with($modulePath, 'phar://')
            ? new PharFileInfo(filename: $modulePath)
            : new SplFileInfo(filename: $modulePath);

        if ($file->isReadable() && $file->isFile()) {
            // Found directory with Module.php in it
            $absModulePath = $this->pharBasePath ? (string) $file : $file->getRealPath();
            if ($absModulePath === false) {
                return false;
            }
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
     * @return  mixed
     *          False [if unable to load $class]
     *          get_class($class) [if $class is successfully loaded]
     */
    protected function loadModuleFromPhar(string $pharPath, string $class): mixed
    {
        $pharPath = static::normalizePath(path: $pharPath, trailingSlash: false);
        $file = new SplFileInfo(filename: $pharPath);
        if (!$file->isReadable() || !$file->isFile()) {
            return false;
        }

        $fileRealPath = $file->getRealPath();

        // Phase 0: Check for executable phar with Module class in stub
        if (str_contains(Assert::string($fileRealPath), '.phar')) {
            // First see if the stub makes the Module class available
            require_once $fileRealPath;
            if (class_exists(class: $class)) {
                $this->moduleClassMap[$class] = $fileRealPath;
                return $class;
            }
        }

        // Phase 1: Not executable phar, no stub, or stub did not provide Module class; try Module.php directly
        $moduleClassFile = 'phar://' . $fileRealPath . '/Module.php';
        $moduleFile = new SplFileInfo(filename: $moduleClassFile);
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
        $pharBaseName = $this->pharFileToModuleName(pharPath: Assert::string($fileRealPath));
        $moduleClassFile = 'phar://' . $fileRealPath . '/' . $pharBaseName . '/Module.php';
        $moduleFile = new SplFileInfo(filename: $moduleClassFile);
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
     */
    public function register(): void
    {
        spl_autoload_register(callback: $this->autoload(...));
    }

    /**
     * Unregister the autoloader with spl_autoload registry
     *
     * @throws \UnexpectedValueException
     */
    public function unregister(): void
    {
        spl_autoload_unregister(callback: $this->autoload(...));
    }

    /**
     * registerPaths
     *
     * @throws InvalidArgumentException
     */
    public function registerPaths(Traversable|array $paths): static
    {
        /**
         * @mago-var mixed $module
         * @mago-var mixed $path
         */
        foreach ($paths as $module => $path) {
            if (is_string(value: $module)) {
                $this->registerPath(path: Assert::string($path), moduleName: $module);
                continue;
            }
            $this->registerPath(path: Assert::string($path));
        }

        return $this;
    }

    /**
     * registerPath
     *
     * @throws InvalidArgumentException
     */
    public function registerPath(string $path, bool|string $moduleName = false): static
    {
        if ($moduleName) {
            // These alternatives are mutually exclusive; the following code consumes the selected state.
            // @mago-expect lint:no-else-clause(1)
            if (
                in_array(
                    needle: substr(string: Assert::string($moduleName), offset: -2),
                    haystack: ['\\*', '\\%'],
                    strict: true,
                )
            ) {
                $this->namespacedPaths[substr(string: Assert::string($moduleName), offset: 0, length: -2)] =
                    static::normalizePath(path: $path);
            } else {
                $this->explicitPaths[$moduleName] = static::normalizePath(path: $path);
            }
            return $this;
        }
        $this->paths[] = static::normalizePath(path: $path);
        return $this;
    }

    /**
     * getPaths
     *
     * This is primarily for unit testing, but could have other uses.
     */
    public function getPaths(): array
    {
        return $this->paths;
    }

    /**
     * Returns the base module name from the path to a phar
     */
    protected function pharFileToModuleName(string $pharPath): string
    {
        do {
            $pathinfo = pathinfo(path: $pharPath);
            $pharPath = $pathinfo['filename'];
        } while (($pathinfo['extension'] ?? null) !== null);
        return $pathinfo['filename'];
    }

    /**
     * Normalize a path for insertion in the stack
     *
     * @param bool $trailingSlash Whether trailing slash should be included
     */
    // Preserve the existing parameter contract used by callers and inherited interfaces.
    // @mago-expect lint:no-boolean-flag-parameter(1)
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
