<?php

declare(strict_types=1);

namespace Jield\ApiTools\OAuth2\Factory;

use Psr\Container\ContainerInterface;

class OAuth2ServerFactory
{
    public function __invoke(ContainerInterface $container): OAuth2ServerInstanceFactory
    {
        $config = $container->get('config');
        $config = $config['api-tools-oauth2'] ?? [];
        return new OAuth2ServerInstanceFactory(config: $config, services: $container);
    }
}
