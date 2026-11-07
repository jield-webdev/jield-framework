<?php

declare(strict_types=1);

namespace Jield\Mvc\Navigation\Service;

use Psr\Container\ContainerInterface;
use Traversable;

/**
 * Constructed factory to set pages during construction.
 *
 * @final
 */
class ConstructedNavigationFactory extends AbstractNavigationFactory
{
    /**
     * @param array<array-key, array<string, mixed>>|Traversable<array-key, mixed> $config
     */
    public function __construct(
        protected mixed $config,
    ) {
    }

    /**
     * @return array<array-key, array<string, mixed>>
     */
    public function getPages(ContainerInterface $container): array
    {
        $this->pages ??= $this->preparePages($container, $this->getPagesFromConfig($this->config));
        return $this->pages;
    }

    public function getName(): string
    {
        return 'constructed';
    }
}
