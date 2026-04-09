<?php

declare(strict_types=1);

namespace Jield\Authorize\Service;

use Jield\Authorize\Service\AuthorizeService;
use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Initializer\InitializerInterface;

/**
 * Initializer that injects a {@see \Jield\Authorize\Service\AuthorizeService} in
 * objects that are instances of {@see \Jield\Authorize\Service\AuthorizeAwareInterface}
 */
class AuthorizeAwareServiceInitializer implements InitializerInterface
{
    /**
     * {@inheritDoc}
     *
     * @see \Laminas\ServiceManager\Initializer\InitializerInterface::__invoke()
     */
    public function __invoke(ContainerInterface $container, $instance): void
    {
        if (! $instance instanceof AuthorizeAwareInterface) {
            return;
        }

        /** @var AuthorizeService $authorize */
        $authorize = $container->get(AuthorizeService::class);

        $instance->setAuthorizeService(auth: $authorize);
    }
}
