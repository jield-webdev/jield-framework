<?php

declare(strict_types=1);

namespace Jield\ApiTools\OAuth2\Factory;

use GuzzleHttp\Client;
use Jield\ApiTools\OAuth2\EntraOnBehalfOfClient;
use Psr\Container\ContainerInterface;

final class EntraOnBehalfOfClientFactory
{
    public function __invoke(ContainerInterface $container): EntraOnBehalfOfClient
    {
        return new EntraOnBehalfOfClient(new Client());
    }
}
