<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation;

use Laminas\Stdlib\AbstractOptions;
use Laminas\Stdlib\Exception\BadMethodCallException;

use Override;
use function array_merge_recursive;
use function str_replace;

class ContentNegotiationOptions extends AbstractOptions
{
    /** @var array */
    protected array $controllers = [];

    /** @var array */
    protected array $selectors = [];

    /** @var array */
    protected array $acceptWhitelist = [];

    /** @var array */
    protected array $contentTypeWhitelist = [];

    /** @var boolean */
    protected bool $xHttpMethodOverrideEnabled = false;

    /** @var array */
    protected array $httpOverrideMethods = [];

    /** @var array */
    private array $keysToNormalize = [
        'accept-whitelist',
        'content-type-whitelist',
        'x-http-method-override-enabled',
        'http-override-methods',
    ];

    /**
     * {@inheritDoc}
     *
     * Normalizes and merges the configuration for specific configuration keys
     *
     * @see self::normalizeOptions
     */
    #[Override]
    public function setFromArray(AbstractOptions|array $options): AbstractOptions
    {
        return parent::setFromArray(
            options: $this->normalizeOptions(config: $options)
        );
    }

    /**
     * This method uses the config keys given in $keyToNormalize to merge
     * the config.
     * It uses Jield's default approach of merging configs, by merging them with
     * `array_merge_recursive()`.
     *
     * @param array $config
     * @return array
     */
    private function normalizeOptions(array $config): array
    {
        $mergedConfig = $config;

        foreach ($this->keysToNormalize as $key) {
            $normalizedKey = $this->normalizeKey(key: $key);

            if (isset($config[$key]) && isset($config[$normalizedKey])) {
                $mergedConfig[$normalizedKey] = array_merge_recursive(
                    $config[$key],
                    $config[$normalizedKey]
                );
                unset($mergedConfig[$key]);
                continue;
            }

            if (isset($config[$key])) {
                $mergedConfig[$normalizedKey] = $config[$key];
                unset($mergedConfig[$key]);
                continue;
            }

            if (isset($config[$normalizedKey])) {
                $mergedConfig[$normalizedKey] = $config[$normalizedKey];
                continue;
            }
        }

        return $mergedConfig;
    }

    /**
     * @param string $key
     * @return string
     *@deprecated since 1.4.0; hhould be removed in next major version, and only one
     *     configuration key style should be supported.
     *
     */
    private function normalizeKey(string $key): string
    {
        return str_replace(search: '-', replace: '_', subject: $key);
    }

    /**
     * {@inheritDoc}
     *
     * Normalizes dash-separated keys to underscore-separated to ensure
     * backwards compatibility with old options (even though dash-separated
     * were previously ignored!).
     *
     * @param string $key
     * @param mixed $value
     * @return void
     * @throws BadMethodCallException
     * @see \Laminas\Stdlib\ParameterObject::__set()
     *
     */
    #[Override]
    public function __set(string $key, $value)
    {
        parent::__set(key: $this->normalizeKey(key: $key), value: $value);
    }

    /**
     * {@inheritDoc}
     *
     * Normalizes dash-separated keys to underscore-separated to ensure
     * backwards compatibility with old options (even though dash-separated
     * were previously ignored!).
     *
     * @param string $key
     * @return mixed
     *@throws BadMethodCallException
     * @see \Laminas\Stdlib\ParameterObject::__get()
     *
     */
    #[Override]
    public function __get(string $key)
    {
        return parent::__get(key: $this->normalizeKey(key: $key));
    }

    /**
     * @param array $controllers
     * @return void
     */
    public function setControllers(array $controllers): void
    {
        $this->controllers = $controllers;
    }

    /**
     * @return array
     */
    public function getControllers(): array
    {
        return $this->controllers;
    }

    /**
     * @param array $selectors
     * @return void
     */
    public function setSelectors(array $selectors): void
    {
        $this->selectors = $selectors;
    }

    /**
     * @return array
     */
    public function getSelectors(): array
    {
        return $this->selectors;
    }

    /**
     * @param array $whitelist
     * @return void
     */
    public function setAcceptWhitelist(array $whitelist): void
    {
        $this->acceptWhitelist = $whitelist;
    }

    /**
     * @return array
     */
    public function getAcceptWhitelist(): array
    {
        return $this->acceptWhitelist;
    }

    /**
     * @param array $whitelist
     * @return void
     */
    public function setContentTypeWhitelist(array $whitelist): void
    {
        $this->contentTypeWhitelist = $whitelist;
    }

    /**
     * @return array
     */
    public function getContentTypeWhitelist(): array
    {
        return $this->contentTypeWhitelist;
    }

    /**
     * @param boolean $xHttpMethodOverrideEnabled
     * @return void
     */
    public function setXHttpMethodOverrideEnabled(bool $xHttpMethodOverrideEnabled): void
    {
        $this->xHttpMethodOverrideEnabled = $xHttpMethodOverrideEnabled;
    }

    /**
     * @return boolean
     */
    public function getXHttpMethodOverrideEnabled(): bool
    {
        return $this->xHttpMethodOverrideEnabled;
    }

    /**
     * @param array $httpOverrideMethods
     * @return void
     */
    public function setHttpOverrideMethods(array $httpOverrideMethods): void
    {
        $this->httpOverrideMethods = $httpOverrideMethods;
    }

    /**
     * @return array
     */
    public function getHttpOverrideMethods(): array
    {
        return $this->httpOverrideMethods;
    }
}
