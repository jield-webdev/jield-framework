<?php

declare(strict_types=1);

namespace Jield\Stdlib;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Psr\Container\ContainerInterface;

/** Resolve services with an explicit contract at the container boundary. */
final class Service
{
    /**
     * @template T of object
     * @param class-string<T> $type
     * @return T
     */
    public static function get(ContainerInterface $container, string $type, ?string $id = null): object
    {
        $service = $container->get($id ?? $type);
        if (!$service instanceof $type) {
            throw new ServiceNotCreatedException(sprintf(
                'Service "%s" must implement %s; received %s',
                $id ?? $type,
                $type,
                get_debug_type($service),
            ));
        }

        return $service;
    }
}
