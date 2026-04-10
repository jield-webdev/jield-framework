<?php

declare(strict_types=1);

namespace Jield\Mvc\Plugin\Identity;

use Jield\Mvc\Plugin\Identity\Factory\IdentityFactory;

class Module
{
    /**
     * Provide application configuration.
     *
     * Adds aliases and factories for the Identity plugin.
     *
     * @return array
     */
    public function getConfig(): array
    {
        return [
            'controller_plugins' => [
                'aliases'   => [
                    'identity' => Identity::class,
                    'Identity' => Identity::class,
                ],
                'factories' => [
                    Identity::class => IdentityFactory::class,
                ],
            ],
        ];
    }
}
