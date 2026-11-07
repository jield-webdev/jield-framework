<?php

declare(strict_types=1);

namespace Jield\ApiTools\OAuth2\Factory;

use Jield\ApiTools\OAuth2\TokenExchange;
use Psr\Container\ContainerInterface;

final class TokenExchangeFactory
{
    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): TokenExchange
    {
        return new TokenExchange(\Webmozart\Assert\Assert::isArray($container->get('config')['api-tools-oauth2'] ?? []));
    }
}
