<?php

declare(strict_types=1);

namespace Jield\Authorize\View\Helper;

use Jield\Authorize\Service\AuthorizeService;
use Jield\Stdlib\Service;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class IsAllowedFactory implements FactoryInterface
{
    /**
     * @param string $requestedName
     * @param array|null $options
     * @return IsAllowed
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): IsAllowed
    {
        $authorize = Service::get($container, AuthorizeService::class);

        return new IsAllowed(authorizeService: $authorize);
    }
}
