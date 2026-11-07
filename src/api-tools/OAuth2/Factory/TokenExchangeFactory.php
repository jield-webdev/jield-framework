<?php

declare(strict_types=1);

namespace Jield\ApiTools\OAuth2\Factory;

use Jield\ApiTools\OAuth2\TokenExchange;
use Psr\Container\ContainerInterface;

final class TokenExchangeFactory
{
    public function __invoke(ContainerInterface $container): TokenExchange
    {
        return new TokenExchange($container->get('config')['api-tools-oauth2'] ?? []);
    }
}
