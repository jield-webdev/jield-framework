<?php

declare(strict_types=1);

namespace Jield\Stdlib;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Psr\Container\ContainerInterface;

/** Resolve services with an explicit contract at the container boundary. */
final class Service
{
    /** @return array<array-key, mixed> */
    public static function config(ContainerInterface $container, string $id = 'config'): array
    {
        return Value::array($container->get($id));
    }

    /**
     * Resolve a service whose factory permits an explicit disabled value.
     * @template T of object
     * @param class-string<T> $type
     * @return T|false
     */
    public static function getOrFalse(ContainerInterface $container, string $type, ?string $id = null): object|false
    {
        /** @mago-var mixed $service */
        $service = $container->get($id ?? $type);

        return $service === false ? false : Value::instance($service, $type);
    }

    /**
     * @template T of object
     * @param class-string<T> $type
     * @return T
     */
    public static function get(ContainerInterface $container, string $type, ?string $id = null): object
    {
        /** @mago-var mixed $service */
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
