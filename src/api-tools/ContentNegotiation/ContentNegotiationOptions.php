<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation;

use Jield\Stdlib\Value;
use Laminas\Stdlib\AbstractOptions;
use Laminas\Stdlib\Exception\BadMethodCallException;
use Override;

use function array_merge_recursive;
use function str_replace;

/**
 * @extends \Laminas\Stdlib\AbstractOptions<mixed>
 */
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
    public function setFromArray($options): AbstractOptions|ContentNegotiationOptions
    {
        return parent::setFromArray(options: Value::stringKeyedArray($this->normalizeOptions(config: Value::array(
            $options,
        ))));
    }

    /**
     * This method uses the config keys given in $keyToNormalize to merge
     * the config.
     * It uses Laminas's default approach of merging configs, by merging them with
     * `array_merge_recursive()`.
     *
     * @param array $config
     * @return array
     */
    private function normalizeOptions(array $config): array
    {
        $mergedConfig = $config;

        /** @var mixed $key */
        foreach ($this->keysToNormalize as $key) {
            /** @var mixed $normalizedKey */
            $normalizedKey = str_replace('-', '_', Value::string($key));

            if (isset($config[$key], $config[$normalizedKey])) {
                $mergedConfig[Value::key($normalizedKey)] = array_merge_recursive(
                    Value::array($config[$key]),
                    Value::array($config[$normalizedKey]),
                );
                unset($mergedConfig[$key]);
                continue;
            }

            if (isset($config[$key])) {
                $mergedConfig[Value::key($normalizedKey)] = $config[$key];
                unset($mergedConfig[$key]);
                continue;
            }

            if (isset($config[$normalizedKey])) {
                $mergedConfig[Value::key($normalizedKey)] = $config[$normalizedKey];
                continue;
            }
        }

        return $mergedConfig;
    }

    /**
     * {@inheritDoc}
     *
     * Normalizes dash-separated keys to underscore-separated to ensure
     * backwards compatibility with old options (even though dash-separated
     * were previously ignored!).
     *
     * @see \Laminas\Stdlib\ParameterObject::__set()
     *
     * @param string $key
     * @param mixed $value
     * @throws BadMethodCallException
     * @return void
     */
    #[Override]
    public function __set($key, $value)
    {
        parent::__set(key: str_replace('-', '_', $key), value: $value);
    }

    /**
     * {@inheritDoc}
     *
     * Normalizes dash-separated keys to underscore-separated to ensure
     * backwards compatibility with old options (even though dash-separated
     * were previously ignored!).
     *
     * @see \Laminas\Stdlib\ParameterObject::__get()
     *
     * @param string $key
     * @throws BadMethodCallException
     * @return mixed
     */
    #[Override]
    public function __get($key)
    {
        return parent::__get(key: str_replace('-', '_', $key));
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
