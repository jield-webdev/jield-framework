<?php

declare(strict_types=1);

namespace Jield\Twig;

use Twig\Error;
use Twig\Loader;
use Twig\Source;
use Webmozart\Assert\Assert;

use function array_key_exists;
use function file_exists;
use function file_get_contents;
use function filemtime;
use function sprintf;

class MapLoader implements Loader\LoaderInterface
{
    protected array $map = [];

    public function add(string $name, string $path): self
    {
        if ($this->exists($name)) {
            throw new Error\LoaderError(sprintf('Name "%s" already exists in map', $name));
        }
        $this->map[$name] = $path;
        return $this;
    }

    public function exists(string $name): bool
    {
        return array_key_exists($name, $this->map);
    }

    public function getSourceContext(string $name): Source
    {
        if (!$this->exists($name)) {
            throw new Error\LoaderError(sprintf('Unable to find template "%s" from template map', $name));
        }
        if (!file_exists(Assert::string($this->map[$name]))) {
            throw new Error\LoaderError(sprintf('Unable to open file "%s" from template map', \Jield\Stdlib\Value::scalar($this->map[$name])));
        }
        return new Source(Assert::string(file_get_contents(Assert::string($this->map[$name]))), $name, Assert::string($this->map[$name]));
    }

    public function getCacheKey(string $name): string
    {
        return $name;
    }

    public function isFresh(string $name, int $time): bool
    {
        $modified = filemtime(Assert::string($this->map[$name]));
        return $modified !== false && $modified <= $time;
    }
}
