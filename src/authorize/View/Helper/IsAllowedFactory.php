<?php

declare(strict_types=1);

namespace Jield\Authorize\View\Helper;

use Jield\Authorize\Service\AuthorizeService;
use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class IsAllowedFactory implements FactoryInterface
{
    /**
     * @param string $requestedName
     * @param array|null $options
     * @return IsAllowed
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): IsAllowed
    {
        /** @var Authorize $authorize */
        $authorize = $container->get(Authorize::class);

        return new IsAllowed(authorizeService: $authorize);
    }
}
