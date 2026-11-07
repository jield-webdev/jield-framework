<?php

declare(strict_types=1);

namespace Jield\GoogleAnalytics\Analytics;

class Event
{
    public function __construct(
        protected string $category,
        protected string $action,
        protected ?string $label = null,
        protected ?string $value = null,
    ) {
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): Event
    {
        $this->category = $category;
        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): Event
    {
        $this->action = $action;
        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): Event
    {
        $this->label = $label;
        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): Event
    {
        $this->value = $value;
        return $this;
    }
}
