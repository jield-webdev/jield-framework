<?php

declare(strict_types=1);

namespace Jield\Mvc\View\Model;

use InvalidArgumentException;

class JsonModel extends ViewModel
{
    protected ?string $jsonpCallback = null;

    public function __construct(
        iterable $variables = [],
        protected array $options = [],
    ) {
        parent::__construct($variables);
        parent::setTerminal(true);
    }

    public function setOption(string $name, mixed $value): static
    {
        $this->options[$name] = $value;
        return $this;
    }

    public function getOption(string $name, mixed $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }

    public function setJsonpCallback(?string $callback): static
    {
        if ($callback !== null && !preg_match('/^[a-zA-Z_$][a-zA-Z0-9_$.]*$/D', $callback)) {
            throw new InvalidArgumentException('Invalid JSONP callback.');
        }
        $this->jsonpCallback = $callback;
        return $this;
    }

    public function getJsonpCallback(): ?string
    {
        return $this->jsonpCallback;
    }

    public function serialize(): string
    {
        $json = json_encode($this->getVariables(), JSON_THROW_ON_ERROR);
        return $this->jsonpCallback === null ? $json : $this->jsonpCallback . '(' . $json . ');';
    }
}
