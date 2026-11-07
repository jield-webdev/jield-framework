<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation;

class ParameterDataContainer
{
    protected array $routeParams = [];

    protected array $queryParams = [];

    protected array $bodyParams = [];

    public function getRouteParams(): array
    {
        return $this->routeParams;
    }

    public function setRouteParams(array $routeParams): static
    {
        $this->routeParams = $routeParams;
        return $this;
    }

    public function hasRouteParam(string $name): bool
    {
        return ($this->routeParams[$name] ?? null) !== null;
    }

    public function getRouteParam(string $name, mixed $default = null): mixed
    {
        return $this->routeParams[$name] ?? $default;
    }

    public function setRouteParam(string $name, mixed $value): static
    {
        $this->routeParams[$name] = $value;
        return $this;
    }

    public function setQueryParams(array $queryParams): static
    {
        $this->queryParams = $queryParams;
        return $this;
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function hasQueryParam(string $name): bool
    {
        return ($this->queryParams[$name] ?? null) !== null;
    }

    public function getQueryParam(string $name, mixed $default = null): mixed
    {
        return $this->queryParams[$name] ?? $default;
    }

    public function setQueryParam(string $name, mixed $value): static
    {
        $this->queryParams[$name] = $value;
        return $this;
    }

    public function setBodyParams(array $bodyParams): static
    {
        $this->bodyParams = $bodyParams;
        return $this;
    }

    public function getBodyParams(): array
    {
        return $this->bodyParams;
    }

    public function hasBodyParam(string $name): bool
    {
        return ($this->bodyParams[$name] ?? null) !== null;
    }

    public function getBodyParam(string $name, mixed $default = null): mixed
    {
        return $this->bodyParams[$name] ?? $default;
    }

    public function setBodyParam(string $name, mixed $value): static
    {
        $this->bodyParams[$name] = $value;
        return $this;
    }
}
