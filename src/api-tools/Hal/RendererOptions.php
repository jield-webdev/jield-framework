<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal;

use Jield\Stdlib\AbstractOptions;

class RendererOptions extends AbstractOptions
{
    protected ?string $defaultHydrator = null;

    protected bool $renderEmbeddedEntities = true;

    protected bool $renderEmbeddedCollections = true;

    protected array $hydrators = [];

    public function setDefaultHydrator(?string $hydrator): void
    {
        $this->defaultHydrator = $hydrator;
    }

    public function getDefaultHydrator(): ?string
    {
        return $this->defaultHydrator;
    }

    public function setRenderEmbeddedEntities(bool $flag): void
    {
        $this->renderEmbeddedEntities = $flag;
    }

    public function getRenderEmbeddedEntities(): bool
    {
        return $this->renderEmbeddedEntities;
    }

    public function setRenderEmbeddedCollections(bool $flag): void
    {
        $this->renderEmbeddedCollections = $flag;
    }

    public function getRenderEmbeddedCollections(): bool
    {
        return $this->renderEmbeddedCollections;
    }

    public function setHydrators(array $hydrators): void
    {
        $this->hydrators = $hydrators;
    }

    public function getHydrators(): array
    {
        return $this->hydrators;
    }
}
