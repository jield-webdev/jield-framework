<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Factory;

use Jield\ApiTools\OAuth2\Adapter\PdoAdapter;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use OAuth2\GrantType\AuthorizationCode;
use OAuth2\GrantType\ClientCredentials;
use OAuth2\GrantType\JwtBearer;
use OAuth2\GrantType\RefreshToken;
use OAuth2\GrantType\UserCredentials;
use OAuth2\OpenID\GrantType\AuthorizationCode as OpenIDAuthorizationCodeGrantType;
use OAuth2\Server as OAuth2Server;
use OAuth2\Storage\AuthorizationCodeInterface;
use OAuth2\Storage\ClientCredentialsInterface;
use OAuth2\Storage\JwtBearerInterface;
use OAuth2\Storage\RefreshTokenInterface;
use OAuth2\Storage\UserCredentialsInterface;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

use function array_key_exists;
use function array_merge;
use function is_array;
use function is_string;
use function strtolower;

// Preserve the existing public API and the state and operations that implement it.
// @mago-expect lint:cyclomatic-complexity(1)
// @mago-expect lint:kan-defect(1)
final class OAuth2ServerFactory
{
    /**
     * Intentionally empty and private to prevent instantiation
     */
    private function __construct()
    {
    }

    /**
     * Create and return a fully configured OAuth2 server instance.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public static function factory(array $config, ContainerInterface $container): OAuth2Server
    {
        /** @mago-var mixed $allConfig */
        $allConfig = $container->get('config');
        /** @mago-var mixed $oauth2Config */
        $oauth2Config = $allConfig['api-tools-oauth2'] ?? [];
        $options = self::marshalOptions(config: Assert::isArray($oauth2Config));

        $oauth2Server = new OAuth2Server(
            storage: self::createStorage(
                config: array_merge(Assert::isArray($oauth2Config), $config),
                container: $container,
            ),
            config: $options,
        );

        return self::injectGrantTypes(
            server: $oauth2Server,
            availableGrantTypes: Assert::isArray($oauth2Config['grant_types']),
            options: $options,
        );
    }

    /**
     * Create and return an OAuth2 storage adapter instance.
     *
     * @return array|PdoAdapter A PdoAdapter, MongoAdapter, or array of storage instances.
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    private static function createStorage(array $config, ContainerInterface $container): PdoAdapter|array
    {
        if (($config['adapter'] ?? null) !== null && is_string(value: $config['adapter'])) {
            return self::createStorageFromAdapter(adapter: $config['adapter'], config: $config, container: $container);
        }

        if (
            ($config['storage'] ?? null) !== null
            && (
                is_string(value: $config['storage'])
                || is_array(value: $config['storage'])
            )
        ) {
            return self::createStorageFromServices(
                storage: \Jield\Stdlib\Value::stringsOrString(
                    is_array($config['storage']) ? $config['storage'] : $config['storage'],
                ),
                container: $container,
            );
        }

        throw new ServiceNotCreatedException(message: 'Missing or invalid storage adapter information for OAuth2');
    }

    /**
     * Create an OAuth2 storage instance based on the adapter specified.
     *
     * @param string $adapter One of "pdo" or "mongo".
     * @throws ServiceNotCreatedException
     */
    private static function createStorageFromAdapter(
        string $adapter,
        array $config,
        ContainerInterface $container,
    ): PdoAdapter {
        return match (strtolower(string: $adapter)) {
            'pdo' => self::createPdoAdapter(config: $config),
            default => throw new ServiceNotCreatedException(message: 'Invalid storage adapter type for OAuth2'),
        };
    }

    /**
     * Creates the OAuth2 storage from services.
     *
     * @param string|string[] $storage A string or an array of strings; each MUST be a valid service.
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    private static function createStorageFromServices(array|string $storage, ContainerInterface $container): array
    {
        $storageServices = [];

        if (is_string(value: $storage)) {
            $storageServices[] = $storage;
        }

        if (is_array(value: $storage)) {
            $storageServices = $storage;
        }

        $storage = [];
        foreach ($storageServices as $key => $service) {
            $storage[$key] = $container->get($service);
        }

        return $storage;
    }

    /**
     * Create and return an OAuth2 PDO adapter.
     *
     * @throws ServiceNotCreatedException
     */
    private static function createPdoAdapter(array $config): PdoAdapter
    {
        return new PdoAdapter(
            connection: self::createPdoConfig(config: $config),
            config: self::getOAuth2ServerConfig(config: $config),
        );
    }

    /**
     * Create and return the configuration needed to create a PDO instance.
     *
     * @throws ServiceNotCreatedException
     */
    private static function createPdoConfig(array $config): array
    {
        if (($config['dsn'] ?? null) === null) {
            throw new ServiceNotCreatedException(message: 'Missing DSN for OAuth2 PDO adapter creation');
        }

        /** @mago-var mixed $username */
        $username = $config['username'] ?? null;
        /** @mago-var mixed $password */
        $password = $config['password'] ?? null;
        /** @mago-var mixed $options */
        $options = $config['options'] ?? [];

        return [
            'dsn' => $config['dsn'],
            'username' => $username,
            'password' => $password,
            'options' => $options,
        ];
    }

    /**
     * Retrieve oauth2-server-php storage settings configuration.
     */
    private static function getOAuth2ServerConfig(array $config): array
    {
        $oauth2ServerConfig = [];
        if (($config['storage_settings'] ?? null) !== null && is_array(value: $config['storage_settings'])) {
            $oauth2ServerConfig = $config['storage_settings'];
        }

        return $oauth2ServerConfig;
    }

    /**
     * Marshal OAuth2\Server options from api-tools-oauth2 configuration.
     */
    private static function marshalOptions(array $config): array
    {
        /** @mago-var mixed $enforceState */
        $enforceState = array_key_exists(key: 'enforce_state', array: $config) ? $config['enforce_state'] : true;
        /** @mago-var mixed $allowImplicit */
        $allowImplicit = $config['allow_implicit'] ?? false;
        /** @mago-var mixed $accessLifetime */
        $accessLifetime = $config['access_lifetime'] ?? 3600;
        /** @mago-var mixed $audience */
        $audience = $config['audience'] ?? '';
        /** @mago-var mixed $options */
        $options = $config['options'] ?? [];

        return array_merge([
            'access_lifetime' => $accessLifetime,
            'allow_implicit' => $allowImplicit,
            'audience' => $audience,
            'enforce_state' => $enforceState,
        ], Assert::isArray($options));
    }

    /**
     * Inject grant types into the OAuth2\Server instance, based on api-tools-oauth2
     * configuration.
     */
    private static function injectGrantTypes(
        OAuth2Server $server,
        array $availableGrantTypes,
        array $options,
    ): OAuth2Server {
        if (
            array_key_exists(key: 'client_credentials', array: $availableGrantTypes)
            && $availableGrantTypes['client_credentials'] === true
        ) {
            $clientOptions = [];
            if (($options['allow_credentials_in_request_body'] ?? null) !== null) {
                $clientOptions['allow_credentials_in_request_body'] = $options['allow_credentials_in_request_body'];
            }

            // Add the "Client Credentials" grant type (it is the simplest of the grant types)
            $server->addGrantType(grantType: new ClientCredentials(
                storage: Assert::isInstanceOf(
                    $server->getStorage(name: 'client_credentials'),
                    ClientCredentialsInterface::class,
                ),
                config: $clientOptions,
            ));
        }

        if (
            array_key_exists(key: 'authorization_code', array: $availableGrantTypes)
            && $availableGrantTypes['authorization_code'] === true
        ) {
            $storage = Assert::isInstanceOf(
                $server->getStorage(name: 'authorization_code'),
                AuthorizationCodeInterface::class,
            );
            $grantType = ($options['use_openid_connect'] ?? false) === true
                ? new OpenIDAuthorizationCodeGrantType($storage)
                : new AuthorizationCode($storage);
            $server->addGrantType(grantType: $grantType);
        }

        if (
            array_key_exists(key: 'password', array: $availableGrantTypes)
            && $availableGrantTypes['password'] === true
        ) {
            // Add the "User Credentials" grant type
            $server->addGrantType(
                grantType: new UserCredentials(storage: Assert::isInstanceOf(
                    $server->getStorage(name: 'user_credentials'),
                    UserCredentialsInterface::class,
                )),
            );
        }

        if (array_key_exists(key: 'jwt', array: $availableGrantTypes) && $availableGrantTypes['jwt'] === true) {
            // Add the "JWT Bearer" grant type
            $server->addGrantType(grantType: new JwtBearer(
                storage: Assert::isInstanceOf($server->getStorage(name: 'jwt_bearer'), JwtBearerInterface::class),
                audience: Assert::string($options['audience']),
            ));
        }

        if (
            array_key_exists(key: 'refresh_token', array: $availableGrantTypes)
            && $availableGrantTypes['refresh_token'] === true
        ) {
            $refreshOptions = [];
            if (($options['always_issue_new_refresh_token'] ?? null) !== null) {
                $refreshOptions['always_issue_new_refresh_token'] = $options['always_issue_new_refresh_token'];
            }

            if (($options['refresh_token_lifetime'] ?? null) !== null) {
                $refreshOptions['refresh_token_lifetime'] = $options['refresh_token_lifetime'];
            }

            if (($options['unset_refresh_token_after_use'] ?? null) !== null) {
                $refreshOptions['unset_refresh_token_after_use'] = $options['unset_refresh_token_after_use'];
            }

            // Add the "Refresh Token" grant type
            $server->addGrantType(grantType: new RefreshToken(
                storage: Assert::isInstanceOf($server->getStorage(name: 'refresh_token'), RefreshTokenInterface::class),
                config: $refreshOptions,
            ));
        }

        return $server;
    }
}
