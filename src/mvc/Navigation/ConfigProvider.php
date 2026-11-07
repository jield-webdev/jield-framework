<?php

declare(strict_types=1);

namespace Jield\Mvc\Navigation;

use Laminas\Navigation\Navigation;

/** @final */
class ConfigProvider
{
    /**
     * Return general-purpose MVC navigation integration configuration.
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencyConfig(),
        ];
    }

    /**
     * Return application-level dependency configuration.
     */
    public function getDependencyConfig(): array
    {
        return [
            'abstract_factories' => [
                Service\NavigationAbstractServiceFactory::class,
            ],
            'aliases' => [
                'navigation' => Navigation::class,
                'Zend\\Navigation\\Navigation' => Navigation::class,
            ],
            'delegators' => [
                'ViewHelperManager' => [
                    View\ViewHelperManagerDelegatorFactory::class,
                ],
            ],
            'factories' => [
                Navigation::class => Service\DefaultNavigationFactory::class,
            ],
        ];
    }
}
