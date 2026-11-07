<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation;

use Laminas\Stdlib\AbstractOptions;
use Laminas\Stdlib\Exception\BadMethodCallException;
use Override;
use Webmozart\Assert\Assert;

use function array_merge_recursive;
use function str_replace;

/**
 * @extends \Laminas\Stdlib\AbstractOptions<mixed>
 */
class ContentNegotiationOptions extends AbstractOptions
{
    protected array $controllers = [];

    protected array $selectors = [];

    protected array $acceptWhitelist = [];

    protected array $contentTypeWhitelist = [];

    protected bool $xHttpMethodOverrideEnabled = false;

    protected array $httpOverrideMethods = [];

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
        return parent::setFromArray(options: \Jield\Stdlib\Value::stringKeyedArray($this->normalizeOptions(config: Assert::isArray(
            $options,
        ))));
    }

    /**
     * This method uses the config keys given in $keyToNormalize to merge
     * the config.
     * It uses Laminas's default approach of merging configs, by merging them with
     * `array_merge_recursive()`.
     */
    private function normalizeOptions(array $config): array
    {
        $mergedConfig = $config;

        /** @mago-var mixed $key */
        foreach ($this->keysToNormalize as $key) {
            $normalizedKey = str_replace('-', '_', Assert::string($key));

            if (($config[$key] ?? null) !== null && ($config[$normalizedKey] ?? null) !== null) {
                $mergedConfig[$normalizedKey] = array_merge_recursive(
                    Assert::isArray($config[$key]),
                    Assert::isArray($config[$normalizedKey]),
                );
                unset($mergedConfig[$key]);
                continue;
            }

            if (($config[$key] ?? null) !== null) {
                $mergedConfig[$normalizedKey] = $config[$key];
                unset($mergedConfig[$key]);
                continue;
            }

            if (($config[$normalizedKey] ?? null) !== null) {
                $mergedConfig[$normalizedKey] = $config[$normalizedKey];
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

    public function setControllers(array $controllers): void
    {
        $this->controllers = $controllers;
    }

    public function getControllers(): array
    {
        return $this->controllers;
    }

    public function setSelectors(array $selectors): void
    {
        $this->selectors = $selectors;
    }

    public function getSelectors(): array
    {
        return $this->selectors;
    }

    public function setAcceptWhitelist(array $whitelist): void
    {
        $this->acceptWhitelist = $whitelist;
    }

    public function getAcceptWhitelist(): array
    {
        return $this->acceptWhitelist;
    }

    public function setContentTypeWhitelist(array $whitelist): void
    {
        $this->contentTypeWhitelist = $whitelist;
    }

    public function getContentTypeWhitelist(): array
    {
        return $this->contentTypeWhitelist;
    }

    public function setXHttpMethodOverrideEnabled(bool $xHttpMethodOverrideEnabled): void
    {
        $this->xHttpMethodOverrideEnabled = $xHttpMethodOverrideEnabled;
    }

    public function getXHttpMethodOverrideEnabled(): bool
    {
        return $this->xHttpMethodOverrideEnabled;
    }

    public function setHttpOverrideMethods(array $httpOverrideMethods): void
    {
        $this->httpOverrideMethods = $httpOverrideMethods;
    }

    public function getHttpOverrideMethods(): array
    {
        return $this->httpOverrideMethods;
    }
}
