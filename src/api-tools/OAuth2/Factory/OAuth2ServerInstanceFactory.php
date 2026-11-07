<?php

declare(strict_types=1);

namespace Jield\ApiTools\OAuth2\Factory;

use Jield\ApiTools\OAuth2\Controller\Exception;
use OAuth2\GrantType\AuthorizationCode;
use OAuth2\GrantType\ClientCredentials;
use OAuth2\GrantType\JwtBearer;
use OAuth2\GrantType\RefreshToken;
use OAuth2\GrantType\UserCredentials;
use OAuth2\Server as OAuth2Server;
use OAuth2\Storage\AuthorizationCodeInterface;
use OAuth2\Storage\ClientCredentialsInterface;
use OAuth2\Storage\JwtBearerInterface;
use OAuth2\Storage\RefreshTokenInterface;
use OAuth2\Storage\UserCredentialsInterface;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

use function array_merge;
use function is_array;
use function is_string;

// Preserve the existing public API and the state and operations that implement it.
// @mago-expect lint:cyclomatic-complexity(1)
class OAuth2ServerInstanceFactory
{
    private ?OAuth2Server $server = null;

    public function __construct(
        private readonly array $config,
        private readonly ContainerInterface $services,
    ) {
    }

    /**
     * @throws Exception\RuntimeException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    // Keep the coordinated parsing or rendering steps of this compatibility method together.
    // @mago-expect lint:halstead(1)
    public function __invoke(): ?OAuth2Server
    {
        if ($this->server instanceof \OAuth2\Server) {
            return $this->server;
        }

        $config = $this->config;

        if (!($config['storage'] ?? null)) {
            throw new Exception\RuntimeException(message: 'The storage configuration for OAuth2 is missing');
        }

        $storagesServices = [];
        // These alternatives are mutually exclusive; the following code consumes the selected state.
        // @mago-expect lint:no-else-clause(2)
        if (is_string(value: $config['storage'])) {
            $storagesServices[] = $config['storage'];
        } elseif (is_array(value: $config['storage'])) {
            $storagesServices = $config['storage'];
        } else {
            throw new Exception\RuntimeException(
                message: 'The storage configuration for OAuth2 should be string or array',
            );
        }

        $storage = [];

        /** @mago-var mixed $storagesService */
        foreach ($storagesServices as $storageKey => $storagesService) {
            $storage[$storageKey] = $this->services->get(Assert::string($storagesService));
        }

        /** @mago-var mixed $enforceState */
        $enforceState = $config['enforce_state'] ?? true;
        /** @mago-var mixed $allowImplicit */
        $allowImplicit = $config['allow_implicit'] ?? false;
        /** @mago-var mixed $accessLifetime */
        $accessLifetime = $config['access_lifetime'] ?? 3600;
        /** @mago-var mixed $audience */
        $audience = $config['audience'] ?? '';
        /** @mago-var mixed $options */
        $options = $config['options'] ?? [];
        $options = array_merge([
            'enforce_state' => $enforceState,
            'allow_implicit' => $allowImplicit,
            'access_lifetime' => $accessLifetime,
        ], Assert::isArray($options));

        // Pass a storage object or array of storage objects to the OAuth2 server class
        $server = new OAuth2Server(storage: $storage, config: $options);
        /** @mago-var mixed $availableGrantTypes */
        $availableGrantTypes = $config['grant_types'];

        if (
            ($availableGrantTypes['client_credentials'] ?? null) !== null
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
            ($availableGrantTypes['authorization_code'] ?? null) !== null
            && $availableGrantTypes['authorization_code'] === true
        ) {
            // Add the "Authorization Code" grant type (this is where the oauth magic happens)
            $server->addGrantType(
                grantType: new AuthorizationCode(storage: Assert::isInstanceOf(
                    $server->getStorage(name: 'authorization_code'),
                    AuthorizationCodeInterface::class,
                )),
            );
        }

        if (($availableGrantTypes['password'] ?? null) !== null && $availableGrantTypes['password'] === true) {
            // Add the "User Credentials" grant type
            $server->addGrantType(
                grantType: new UserCredentials(storage: Assert::isInstanceOf(
                    $server->getStorage(name: 'user_credentials'),
                    UserCredentialsInterface::class,
                )),
            );
        }

        if (($availableGrantTypes['jwt'] ?? null) !== null && $availableGrantTypes['jwt'] === true) {
            // Add the "JWT Bearer" grant type
            $server->addGrantType(grantType: new JwtBearer(
                storage: Assert::isInstanceOf($server->getStorage(name: 'jwt_bearer'), JwtBearerInterface::class),
                audience: Assert::string($audience),
            ));
        }

        if (
            ($availableGrantTypes['refresh_token'] ?? null) !== null
            && $availableGrantTypes['refresh_token'] === true
        ) {
            $refreshOptions = [];
            if (($options['always_issue_new_refresh_token'] ?? null) !== null) {
                $refreshOptions['always_issue_new_refresh_token'] = $options['always_issue_new_refresh_token'];
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

        return $this->server = $server;
    }
}
