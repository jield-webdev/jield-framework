<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation;

use Laminas\Stdlib\AbstractOptions;

class ContentNegotiationOptions extends AbstractOptions
{
    protected array $controllers = [];

    protected array $selectors = [];

    protected array $acceptWhitelist = [];

    protected array $contentTypeWhitelist = [];

    protected bool $xHttpMethodOverrideEnabled = false;

    protected array $httpOverrideMethods = [];

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
