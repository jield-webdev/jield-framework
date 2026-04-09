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
use Psr\Container\ContainerInterface;
use function array_key_exists;
use function array_merge;
use function is_array;
use function is_string;
use function strtolower;

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
     */
    public static function factory(array $config, ContainerInterface $container): OAuth2Server
    {
        $allConfig    = $container->get('config');
        $oauth2Config = $allConfig['api-tools-oauth2'] ?? [];
        $options      = self::marshalOptions(config: $oauth2Config);

        $oauth2Server = new OAuth2Server(
            storage: self::createStorage(config: array_merge($oauth2Config, $config), container: $container),
            config: $options
        );

        return self::injectGrantTypes(server: $oauth2Server, availableGrantTypes: $oauth2Config['grant_types'], options: $options);
    }

    /**
     * Create and return an OAuth2 storage adapter instance.
     *
     * @return array|PdoAdapter A PdoAdapter, MongoAdapter, or array of storage instances.
     */
    private static function createStorage(array $config, ContainerInterface $container): PdoAdapter|array
    {
        if (isset($config['adapter']) && is_string(value: $config['adapter'])) {
            return self::createStorageFromAdapter(adapter: $config['adapter'], config: $config, container: $container);
        }

        if (
            isset($config['storage'])
            && (is_string(value: $config['storage']) || is_array(value: $config['storage']))
        ) {
            return self::createStorageFromServices(storage: $config['storage'], container: $container);
        }

        throw new ServiceNotCreatedException(message: 'Missing or invalid storage adapter information for OAuth2');
    }

    /**
     * Create an OAuth2 storage instance based on the adapter specified.
     *
     * @param string $adapter One of "pdo" or "mongo".
     */
    private static function createStorageFromAdapter(string $adapter, array $config, ContainerInterface $container): PdoAdapter
    {
        return match (strtolower(string: $adapter)) {
            'pdo'   => self::createPdoAdapter(config: $config),
            default => throw new ServiceNotCreatedException(message: 'Invalid storage adapter type for OAuth2'),
        };
    }

    /**
     * Creates the OAuth2 storage from services.
     *
     * @param string|string[] $storage A string or an array of strings; each MUST be a valid service.
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
     */
    private static function createPdoAdapter(array $config): PdoAdapter
    {
        return new PdoAdapter(
            connection: self::createPdoConfig(config: $config),
            config: self::getOAuth2ServerConfig(config: $config)
        );
    }

    /**
     * Create and return the configuration needed to create a PDO instance.
     *
     * @param array $config
     * @return array
     */
    private static function createPdoConfig(array $config): array
    {
        if (!isset($config['dsn'])) {
            throw new ServiceNotCreatedException(
                message: 'Missing DSN for OAuth2 PDO adapter creation'
            );
        }

        $username = $config['username'] ?? null;
        $password = $config['password'] ?? null;
        $options  = $config['options'] ?? [];

        return [
            'dsn'      => $config['dsn'],
            'username' => $username,
            'password' => $password,
            'options'  => $options,
        ];
    }

    /**
     * Retrieve oauth2-server-php storage settings configuration.
     */
    private static function getOAuth2ServerConfig(array $config): array
    {
        $oauth2ServerConfig = [];
        if (isset($config['storage_settings']) && is_array(value: $config['storage_settings'])) {
            $oauth2ServerConfig = $config['storage_settings'];
        }

        return $oauth2ServerConfig;
    }

    /**
     * Marshal OAuth2\Server options from api-tools-oauth2 configuration.
     *
     * @param array $config
     * @return array
     */
    private static function marshalOptions(array $config): array
    {
        $enforceState   = array_key_exists(key: 'enforce_state', array: $config)
            ? $config['enforce_state']
            : true;
        $allowImplicit  = $config['allow_implicit'] ?? false;
        $accessLifetime = $config['access_lifetime'] ?? 3600;
        $audience       = $config['audience'] ?? '';
        $options        = $config['options'] ?? [];

        return array_merge([
            'access_lifetime' => $accessLifetime,
            'allow_implicit'  => $allowImplicit,
            'audience'        => $audience,
            'enforce_state'   => $enforceState,
        ], $options);
    }

    /**
     * Inject grant types into the OAuth2\Server instance, based on api-tools-oauth2
     * configuration.
     *
     */
    private static function injectGrantTypes(OAuth2Server $server, array $availableGrantTypes, array $options): OAuth2Server
    {
        if (
            array_key_exists(key: 'client_credentials', array: $availableGrantTypes)
            && $availableGrantTypes['client_credentials'] === true
        ) {
            $clientOptions = [];
            if (isset($options['allow_credentials_in_request_body'])) {
                $clientOptions['allow_credentials_in_request_body'] = $options['allow_credentials_in_request_body'];
            }

            // Add the "Client Credentials" grant type (it is the simplest of the grant types)
            $server->addGrantType(grantType: new ClientCredentials(storage: $server->getStorage(name: 'client_credentials'), config: $clientOptions));
        }

        if (
            array_key_exists(key: 'authorization_code', array: $availableGrantTypes)
            && $availableGrantTypes['authorization_code'] === true
        ) {
            $authCodeClass = array_key_exists(key: 'use_openid_connect', array: $options) && $options['use_openid_connect'] === true
                ? OpenIDAuthorizationCodeGrantType::class
                : AuthorizationCode::class;

            // Add the "Authorization Code" grant type (this is where the oauth magic happens)
            $server->addGrantType(grantType: new $authCodeClass($server->getStorage(name: 'authorization_code')));
        }

        if (array_key_exists(key: 'password', array: $availableGrantTypes) && $availableGrantTypes['password'] === true) {
            // Add the "User Credentials" grant type
            $server->addGrantType(grantType: new UserCredentials(storage: $server->getStorage(name: 'user_credentials')));
        }

        if (array_key_exists(key: 'jwt', array: $availableGrantTypes) && $availableGrantTypes['jwt'] === true) {
            // Add the "JWT Bearer" grant type
            $server->addGrantType(grantType: new JwtBearer(storage: $server->getStorage(name: 'jwt_bearer'), audience: $options['audience']));
        }

        if (array_key_exists(key: 'refresh_token', array: $availableGrantTypes) && $availableGrantTypes['refresh_token'] === true) {
            $refreshOptions = [];
            if (isset($options['always_issue_new_refresh_token'])) {
                $refreshOptions['always_issue_new_refresh_token'] = $options['always_issue_new_refresh_token'];
            }

            if (isset($options['refresh_token_lifetime'])) {
                $refreshOptions['refresh_token_lifetime'] = $options['refresh_token_lifetime'];
            }

            if (isset($options['unset_refresh_token_after_use'])) {
                $refreshOptions['unset_refresh_token_after_use'] = $options['unset_refresh_token_after_use'];
            }

            // Add the "Refresh Token" grant type
            $server->addGrantType(grantType: new RefreshToken(storage: $server->getStorage(name: 'refresh_token'), config: $refreshOptions));
        }

        return $server;
    }
}
