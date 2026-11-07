<?php

declare(strict_types=1);

namespace Jield\ApiTools\OAuth2\Factory;

use Psr\Container\ContainerInterface;

class OAuth2ServerFactory
{
    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): OAuth2ServerInstanceFactory
    {
        /** @mago-var mixed $config */
        $config = $container->get('config');
        /** @mago-var mixed $config */
        $config = $config['api-tools-oauth2'] ?? [];
        return new OAuth2ServerInstanceFactory(
            config: \Webmozart\Assert\Assert::isArray($config),
            services: $container,
        );
    }
}
