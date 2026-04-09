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
use Psr\Container\ContainerInterface;
use function array_merge;
use function is_array;
use function is_string;

class OAuth2ServerInstanceFactory
{
    private ?OAuth2Server $server = null;

    public function __construct(private readonly array $config, private readonly ContainerInterface $services)
    {
    }

    public function __invoke(): ?OAuth2Server
    {
        if ($this->server instanceof \OAuth2\Server) {
            return $this->server;
        }

        $config = $this->config;

        if (empty($config['storage'])) {
            throw new Exception\RuntimeException(
                message: 'The storage configuration for OAuth2 is missing'
            );
        }

        $storagesServices = [];
        if (is_string(value: $config['storage'])) {
            $storagesServices[] = $config['storage'];
        } elseif (is_array(value: $config['storage'])) {
            $storagesServices = $config['storage'];
        } else {
            throw new Exception\RuntimeException(
                message: 'The storage configuration for OAuth2 should be string or array'
            );
        }

        $storage = [];

        foreach ($storagesServices as $storageKey => $storagesService) {
            $storage[$storageKey] = $this->services->get($storagesService);
        }

        $enforceState   = $config['enforce_state'] ?? true;
        $allowImplicit  = $config['allow_implicit'] ?? false;
        $accessLifetime = $config['access_lifetime'] ?? 3600;
        $audience       = $config['audience'] ?? '';
        $options        = $config['options'] ?? [];
        $options        = array_merge([
            'enforce_state'   => $enforceState,
            'allow_implicit'  => $allowImplicit,
            'access_lifetime' => $accessLifetime,
        ], $options);

        // Pass a storage object or array of storage objects to the OAuth2 server class
        $server              = new OAuth2Server(storage: $storage, config: $options);
        $availableGrantTypes = $config['grant_types'];

        if (isset($availableGrantTypes['client_credentials']) && $availableGrantTypes['client_credentials'] === true) {
            $clientOptions = [];
            if (isset($options['allow_credentials_in_request_body'])) {
                $clientOptions['allow_credentials_in_request_body'] = $options['allow_credentials_in_request_body'];
            }

            // Add the "Client Credentials" grant type (it is the simplest of the grant types)
            $server->addGrantType(grantType: new ClientCredentials(storage: $server->getStorage(name: 'client_credentials'), config: $clientOptions));
        }

        if (isset($availableGrantTypes['authorization_code']) && $availableGrantTypes['authorization_code'] === true) {
            // Add the "Authorization Code" grant type (this is where the oauth magic happens)
            $server->addGrantType(grantType: new AuthorizationCode(storage: $server->getStorage(name: 'authorization_code')));
        }

        if (isset($availableGrantTypes['password']) && $availableGrantTypes['password'] === true) {
            // Add the "User Credentials" grant type
            $server->addGrantType(grantType: new UserCredentials(storage: $server->getStorage(name: 'user_credentials')));
        }

        if (isset($availableGrantTypes['jwt']) && $availableGrantTypes['jwt'] === true) {
            // Add the "JWT Bearer" grant type
            $server->addGrantType(grantType: new JwtBearer(storage: $server->getStorage(name: 'jwt_bearer'), audience: $audience));
        }

        if (isset($availableGrantTypes['refresh_token']) && $availableGrantTypes['refresh_token'] === true) {
            $refreshOptions = [];
            if (isset($options['always_issue_new_refresh_token'])) {
                $refreshOptions['always_issue_new_refresh_token'] = $options['always_issue_new_refresh_token'];
            }

            if (isset($options['unset_refresh_token_after_use'])) {
                $refreshOptions['unset_refresh_token_after_use'] = $options['unset_refresh_token_after_use'];
            }

            // Add the "Refresh Token" grant type
            $server->addGrantType(grantType: new RefreshToken(storage: $server->getStorage(name: 'refresh_token'), config: $refreshOptions));
        }

        return $this->server = $server;
    }
}
