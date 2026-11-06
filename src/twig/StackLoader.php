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
        $this->defaultSuffix = $defaultSuffix;
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
        $name = preg_replace('#/{2,}#', '/', str_replace('\\', '/', $name))
            ?? throw new Error\LoaderError('Unable to normalize the template name.');
        $defaultSuffix = $this->getDefaultSuffix();
        if (pathinfo($name, PATHINFO_EXTENSION) !== $defaultSuffix) {
            $name .= '.' . $defaultSuffix;
        }

        return parent::findTemplate($name, $throw);
    }
}
