<?php

declare(strict_types=1);

namespace Jield\Mvc\Navigation\Service;

/**
 * Default navigation factory.
 *
 * @final
 */
class DefaultNavigationFactory extends AbstractNavigationFactory
{
    /**
     * @return string
     */
    protected function getName(): string
    {
        return 'default';
    }
}
