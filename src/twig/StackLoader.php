<?php

declare(strict_types=1);

namespace Jield\Twig;

use Twig\Error;
use Twig\Loader;
use function explode;
use function implode;
use function is_file;
use function ltrim;
use function pathinfo;
use function preg_replace;
use function sprintf;
use function strpos;
use function substr;
use const PATHINFO_EXTENSION;

class StackLoader extends Loader\FilesystemLoader
{
    /**
     * Default suffix to use
     *
     * Appends this suffix if the template requested does not use it.
     *
     * @var string
     */
    protected string $defaultSuffix = 'twig';

    /**
     * Set default file suffix
     *
     * @return StackLoader
     */
    public function setDefaultSuffix(string $defaultSuffix): self
    {
        $this->defaultSuffix = (string)$defaultSuffix;
        $this->defaultSuffix = ltrim(string: $this->defaultSuffix, characters: '.');

        return $this;
    }

    /**
     * Get default file suffix
     */
    public function getDefaultSuffix(): string
    {
        return $this->defaultSuffix;
    }

    /**
     * @throws Error\LoaderError
     */
    protected function findTemplate(string $name, bool $throw = true): ?string
    {
        $name = (string)$name;

        // normalize name
        $name = preg_replace(pattern: '#/{2,}#', replacement: '/', subject: str_replace(search: '\\', replace: '/', subject: $name));

        // Ensure we have the expected file extension
        $defaultSuffix = $this->getDefaultSuffix();
        if (pathinfo(path: $name, flags: PATHINFO_EXTENSION) !== $defaultSuffix) {
            $name .= '.' . $defaultSuffix;
        }

        if (isset($this->cache[$name])) {
            return $this->cache[$name];
        }

        if (isset($this->errorCache[$name])) {
            if (!$throw) {
                return $this->cache[$name] = null;
            }
            throw new Error\LoaderError(message: $this->errorCache[$name]);
        }

        $this->validateName(name: $name);

        $namespace = '__main__';
        if (isset($name[0]) && '@' === $name[0]) {
            if (false === $pos = strpos(haystack: $name, needle: '/')) {
                $this->errorCache[$name] = sprintf(
                    'Malformed namespaced template name "%s" (expecting "@namespace/template_name").',
                    $name
                );

                if (!$throw) {
                    return $this->cache[$name] = null;
                }

                throw new Error\LoaderError(message: $this->errorCache[$name]);
            }

            $namespace = substr(string: $name, offset: 1, length: $pos - 1);

            $name = substr(string: $name, offset: $pos + 1);
        }

        if (!isset($this->paths[$namespace])) {
            $this->errorCache[$name] = sprintf('There are no registered paths for namespace "%s".', $namespace);

            if (!$throw) {
                return $this->cache[$name] = null;
            }
            throw new Error\LoaderError(message: $this->errorCache[$name]);
        }

        foreach ($this->paths[$namespace] as $path) {
            if (is_file(filename: $path . '/' . $name)) {
                return $this->cache[$name] = $path . '/' . $name;
            }
        }

        $this->errorCache[$name] = sprintf(
            'Unable to find template "%s" (looked into: %s).',
            $name,
            implode(
                separator: ', ',
                array: $this->paths[$namespace]
            )
        );

        if (!$throw) {
            return $this->cache[$name] = null;
        }

        throw new Error\LoaderError(message: $this->errorCache[$name]);
    }

    /**
     * @throws Error\LoaderError
     */
    private function validateName(string $name): void
    {
        if (str_contains(haystack: $name, needle: "\0")) {
            throw new Error\LoaderError(message: 'A template name cannot contain NUL bytes.');
        }

        $name  = ltrim(string: $name, characters: '/');
        $parts = explode(separator: '/', string: $name);
        $level = 0;
        foreach ($parts as $part) {
            if ('..' === $part) {
                --$level;
            } elseif ('.' !== $part) {
                ++$level;
            }

            if ($level < 0) {
                throw new Error\LoaderError(
                    message: sprintf(
                        'Looks like you try to load a template outside configured directories (%s).',
                        $name
                    )
                );
            }
        }
    }
}
