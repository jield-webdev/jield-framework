<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Factory;

use Jield\Authentication\Adapter\Http as HttpAuth;
use Jield\Authentication\Adapter\Http\ApacheResolver;
use Jield\Authentication\Adapter\Http\FileResolver;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Psr\Container\ContainerInterface;

use function array_merge;
use function implode;
use function in_array;
use function is_array;
use function is_string;

/**
 * Create and return a Jield\Authentication\Adapter\Http instance based on the
 * configuration provided.
 */
final class HttpAdapterFactory
{
    /**
     * Only defined in order to prevent instantiation.
     */
    private function __construct()
    {
    }

    /**
     * Create an HttpAuth instance based on the configuration passed.
     *
     */
    public static function factory(array $config, ?ContainerInterface $container = null): HttpAuth
    {
        if (! isset($config['accept_schemes']) || ! is_array(value: $config['accept_schemes'])) {
            throw new ServiceNotCreatedException(
                message: '"accept_schemes" is required when configuring an HTTP authentication adapter'
            );
        }

        if (! isset($config['realm'])) {
            throw new ServiceNotCreatedException(
                message: '"realm" is required when configuring an HTTP authentication adapter'
            );
        }

        if (in_array(needle: 'digest', haystack: $config['accept_schemes']) && (! isset($config['digest_domains'])
        || ! isset($config['nonce_timeout']))) {
            throw new ServiceNotCreatedException(
                message: 'Both "digest_domains" and "nonce_timeout" are required '
                . 'when configuring an HTTP digest authentication adapter'
            );
        }

        $httpAdapter = new HttpAuth(config: array_merge(
            $config,
            [
                'accept_schemes' => implode(separator: ' ', array: $config['accept_schemes']),
            ]
        ));

        if (in_array(needle: 'basic', haystack: $config['accept_schemes'])) {
            if (
                isset($config['basic_resolver_factory'])
                && self::containerHasKey(container: $container, key: $config['basic_resolver_factory'])
            ) {
                $httpAdapter->setBasicResolver(resolver: $container->get($config['basic_resolver_factory']));
            } elseif (isset($config['htpasswd'])) {
                $httpAdapter->setBasicResolver(resolver: new ApacheResolver(path: $config['htpasswd']));
            }
        }

        if (in_array(needle: 'digest', haystack: $config['accept_schemes'])) {
            if (
                isset($config['digest_resolver_factory'])
                && self::containerHasKey(container: $container, key: $config['digest_resolver_factory'])
            ) {
                $httpAdapter->setDigestResolver(resolver: $container->get($config['digest_resolver_factory']));
            } elseif (isset($config['htdigest'])) {
                $httpAdapter->setDigestResolver(resolver: new FileResolver(path: $config['htdigest']));
            }
        }

        return $httpAdapter;
    }

    /**
     * @param null $key
     */
    private static function containerHasKey(?ContainerInterface $container = null, null $key = null): bool
    {
        if (! $container instanceof ContainerInterface) {
            return false;
        }

        if (! is_string(value: $key)) {
            return false;
        }

        return $container->has(id: $key);
    }
}
