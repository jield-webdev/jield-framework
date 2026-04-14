<?php

declare(strict_types=1);

namespace Jield\Twig;

use Laminas\Stdlib\AbstractOptions;

class ModuleOptions extends AbstractOptions
{
    protected string $environmentLoader = '';

    protected string $environmentClass = '';

    protected array $environmentOptions = [];

    protected array $globals = [];

    protected array $loaderChain = [];

    protected array $extensions = [];

    protected string $suffix = '';

    protected bool $enableFallbackFunctions = true;

    protected array $helperManager = [];

    public function getEnvironmentLoader(): string
    {
        return $this->environmentLoader;
    }

    public function setEnvironmentLoader(string $environmentLoader): ModuleOptions
    {
        $this->environmentLoader = $environmentLoader;
        return $this;
    }

    public function getEnvironmentClass(): string
    {
        return $this->environmentClass;
    }

    public function setEnvironmentClass(string $environmentClass): ModuleOptions
    {
        $this->environmentClass = $environmentClass;
        return $this;
    }

    public function getEnvironmentOptions(): array
    {
        return $this->environmentOptions;
    }

    public function setEnvironmentOptions(array $environmentOptions): ModuleOptions
    {
        $this->environmentOptions = $environmentOptions;
        return $this;
    }

    public function getGlobals(): array
    {
        return $this->globals;
    }

    public function setGlobals(array $globals): ModuleOptions
    {
        $this->globals = $globals;
        return $this;
    }

    public function getLoaderChain(): array
    {
        return $this->loaderChain;
    }

    public function setLoaderChain(array $loaderChain): ModuleOptions
    {
        $this->loaderChain = $loaderChain;
        return $this;
    }

    public function getExtensions(): array
    {
        return $this->extensions;
    }

    public function setExtensions(array $extensions): ModuleOptions
    {
        $this->extensions = $extensions;
        return $this;
    }

    public function getSuffix(): string
    {
        return $this->suffix;
    }

    public function setSuffix(string $suffix): ModuleOptions
    {
        $this->suffix = $suffix;
        return $this;
    }

    public function enableFallbackFunctions(): bool
    {
        return $this->enableFallbackFunctions;
    }

    public function setEnableFallbackFunctions(bool $enableFallbackFunctions): ModuleOptions
    {
        $this->enableFallbackFunctions = $enableFallbackFunctions;
        return $this;
    }

    public function getHelperManager(): array
    {
        return $this->helperManager;
    }

    public function setHelperManager(array $helperManager): ModuleOptions
    {
        $this->helperManager = $helperManager;
        return $this;
    }
}
