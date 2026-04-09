<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation;

class ParameterDataContainer
{
    /** @var array */
    protected array $routeParams = [];

    /** @var array */
    protected array $queryParams = [];

    /** @var array */
    protected array $bodyParams = [];

    /**
     * @return array
     */
    public function getRouteParams(): array
    {
        return $this->routeParams;
    }

    /**
     * @param  array $routeParams
     * @return static
     */
    public function setRouteParams(array $routeParams): static
    {
        $this->routeParams = $routeParams;
        return $this;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasRouteParam(string $name): bool
    {
        return isset($this->routeParams[$name]);
    }

    /**
     * @param string $name
     * @param mixed|null $default
     * @return mixed
     */
    public function getRouteParam(string $name, mixed $default = null): mixed
    {
        return $this->routeParams[$name] ?? $default;
    }

    /**
     * @param string $name
     */
    public function setRouteParam(string $name, mixed $value): static
    {
        $this->routeParams[$name] = $value;
        return $this;
    }

    /**
     * @param  array $queryParams
     * @return static
     */
    public function setQueryParams(array $queryParams): static
    {
        $this->queryParams = $queryParams;
        return $this;
    }

    /**
     * @return array
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasQueryParam(string $name): bool
    {
        return isset($this->queryParams[$name]);
    }

    /**
     * @param string $name
     * @param mixed|null $default
     * @return mixed
     */
    public function getQueryParam(string $name, mixed $default = null): mixed
    {
        return $this->queryParams[$name] ?? $default;
    }

    /**
     * @param string $name
     */
    public function setQueryParam(string $name, mixed $value): static
    {
        $this->queryParams[$name] = $value;
        return $this;
    }

    /**
     * @param  array $bodyParams
     * @return static
     */
    public function setBodyParams(array $bodyParams): static
    {
        $this->bodyParams = $bodyParams;
        return $this;
    }

    /**
     * @return array
     */
    public function getBodyParams(): array
    {
        return $this->bodyParams;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasBodyParam(string $name): bool
    {
        return isset($this->bodyParams[$name]);
    }

    /**
     * @param string $name
     * @param mixed|null $default
     * @return mixed
     */
    public function getBodyParam(string $name, mixed $default = null): mixed
    {
        return $this->bodyParams[$name] ?? $default;
    }

    /**
     * @param string $name
     */
    public function setBodyParam(string $name, mixed $value): static
    {
        $this->bodyParams[$name] = $value;
        return $this;
    }
}
