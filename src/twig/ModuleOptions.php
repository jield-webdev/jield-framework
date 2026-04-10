<?php

declare(strict_types=1);

namespace Jield\Twig;

use Laminas\Stdlib\AbstractOptions;

class ModuleOptions extends AbstractOptions
{
    /** @var string */
    protected $environmentLoader = '';

    /** @var string */
    protected $environmentClass = '';

    /** @var array */
    protected $environmentOptions = [];

    /** @var array */
    protected $globals = [];

    /** @var array */
    protected $loaderChain = [];

    /** @var array */
    protected $extensions = [];

    /** @var string */
    protected $suffix = '';

    /** @var bool */
    protected $enableFallbackFunctions = true;

    /** @var bool */
    protected $disableZfModel = true;

    /** @var array */
    protected $helperManager = [];

    /**
     * @return $this
     */
    public function setDisableZfModel(bool $disableZfModel): self
    {
        $this->disableZfModel = $disableZfModel;
        return $this;
    }

    public function getDisableZfModel(): bool
    {
        return $this->disableZfModel;
    }

    /**
     * @return $this
     */
    public function setEnableFallbackFunctions(bool $enableFallbackFunctions): self
    {
        $this->enableFallbackFunctions = $enableFallbackFunctions;
        return $this;
    }

    public function getEnableFallbackFunctions(): bool
    {
        return $this->enableFallbackFunctions;
    }

    /**
     * @return $this
     */
    public function setEnvironmentLoader(string $environmentLoader): self
    {
        $this->environmentLoader = $environmentLoader;
        return $this;
    }

    public function getEnvironmentLoader(): string
    {
        return $this->environmentLoader;
    }

    /**
     * @param array $environmentOptions
     * @return $this
     */
    public function setEnvironmentOptions(array $environmentOptions): self
    {
        $this->environmentOptions = $environmentOptions;
        return $this;
    }

    /**
     * @return array
     */
    public function getEnvironmentOptions(): array
    {
        return $this->environmentOptions;
    }

    /**
     * @param array $extensions
     * @return $this
     */
    public function setExtensions(array $extensions): self
    {
        $this->extensions = $extensions;
        return $this;
    }

    /**
     * @return array
     */
    public function getExtensions(): array
    {
        return $this->extensions;
    }

    /**
     * @param array $helperManager
     * @return $this
     */
    public function setHelperManager(array $helperManager): self
    {
        $this->helperManager = $helperManager;
        return $this;
    }

    /**
     * @return array
     */
    public function getHelperManager(): array
    {
        return $this->helperManager;
    }

    /**
     * @param array $loaderChain
     * @return $this
     */
    public function setLoaderChain(array $loaderChain): self
    {
        $this->loaderChain = $loaderChain;
        return $this;
    }

    /**
     * @return array
     */
    public function getLoaderChain(): array
    {
        return $this->loaderChain;
    }

    /**
     * @return $this
     */
    public function setSuffix(string $suffix): self
    {
        $this->suffix = $suffix;
        return $this;
    }

    public function getSuffix(): string
    {
        return $this->suffix;
    }

    public function setEnvironmentClass(string $environmentClass): self
    {
        $this->environmentClass = $environmentClass;
        return $this;
    }

    public function getEnvironmentClass(): string
    {
        return $this->environmentClass;
    }

    /**
     * @param array $globals
     */
    public function setGlobals(array $globals): self
    {
        $this->globals = $globals;
        return $this;
    }

    /**
     * @return array
     */
    public function getGlobals(): array
    {
        return $this->globals;
    }
}
