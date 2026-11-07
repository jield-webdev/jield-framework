<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Factory;

use Laminas\Authentication\Adapter\Http as HttpAuth;
use Laminas\Authentication\Adapter\Http\ApacheResolver;
use Laminas\Authentication\Adapter\Http\FileResolver;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

use function array_merge;
use function implode;
use function in_array;
use function is_array;
use function is_string;

/**
 * Create and return a Laminas\Authentication\Adapter\Http instance based on the
 * configuration provided.
 */
final class HttpAdapterFactory
{
    /**
     * Only defined in order to prevent instantiation.
     */
    private function __construct() {}

    /**
     * Create an HttpAuth instance based on the configuration passed.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public static function factory(array $config, ?ContainerInterface $container = null): HttpAuth
    {
        if (!isset($config['accept_schemes']) || !is_array(value: $config['accept_schemes'])) {
            throw new ServiceNotCreatedException(
                message: '"accept_schemes" is required when configuring an HTTP authentication adapter',
            );
        }

        if (!isset($config['realm'])) {
            throw new ServiceNotCreatedException(
                message: '"realm" is required when configuring an HTTP authentication adapter',
            );
        }

        if (
            in_array(needle: 'digest', haystack: $config['accept_schemes'], strict: true)
            && (!isset($config['digest_domains']) || !isset($config['nonce_timeout']))
        ) {
            throw new ServiceNotCreatedException(message: 'Both "digest_domains" and "nonce_timeout" are required '
            . 'when configuring an HTTP digest authentication adapter');
        }

        $httpAdapter = new HttpAuth(config: array_merge($config, [
            'accept_schemes' => implode(separator: ' ', array: \Jield\Stdlib\Value::scalars($config['accept_schemes'])),
        ]));

        if (in_array(needle: 'basic', haystack: $config['accept_schemes'], strict: true)) {
            if (
                isset($config['basic_resolver_factory'])
                && self::containerHasKey(container: $container, key: $config['basic_resolver_factory'])
            ) {
                $httpAdapter->setBasicResolver(resolver: Assert::isInstanceOf(Assert::notNull($container)->get(
                    Assert::string($config['basic_resolver_factory']),
                ), \Laminas\Authentication\Adapter\Http\ResolverInterface::class));
            } elseif (isset($config['htpasswd'])) {
                $httpAdapter->setBasicResolver(resolver: new ApacheResolver(path: Assert::string($config['htpasswd'])));
            }
        }

        if (in_array(needle: 'digest', haystack: $config['accept_schemes'], strict: true)) {
            if (
                isset($config['digest_resolver_factory'])
                && self::containerHasKey(container: $container, key: $config['digest_resolver_factory'])
            ) {
                $httpAdapter->setDigestResolver(resolver: Assert::isInstanceOf(Assert::notNull($container)->get(
                    Assert::string($config['digest_resolver_factory']),
                ), \Laminas\Authentication\Adapter\Http\ResolverInterface::class));
            } elseif (isset($config['htdigest'])) {
                $httpAdapter->setDigestResolver(resolver: new FileResolver(path: Assert::string($config['htdigest'])));
            }
        }

        return $httpAdapter;
    }

    /**
     * @param mixed $key
     */
    private static function containerHasKey(?ContainerInterface $container = null, $key = null): bool
    {
        if (!$container instanceof ContainerInterface) {
            return false;
        }

        if (!is_string(value: $key)) {
            return false;
        }

        return $container->has($key);
    }
}
