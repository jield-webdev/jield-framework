<?php

declare(strict_types=1);

namespace Jield\ApiTools\OAuth2;

use OAuth2\Encryption\Jwt;

// Preserve the existing public API and the state and operations that implement it.
// @mago-expect lint:cyclomatic-complexity(1)
final readonly class TokenExchange
{
    public const string GRANT_TYPE = 'urn:ietf:params:oauth:grant-type:token-exchange';
    // This is a public protocol or form identifier, not a credential.
    // @mago-expect lint:no-literal-password(1)
    public const string ACCESS_TOKEN = 'urn:ietf:params:oauth:token-type:access_token';

    public function __construct(
        private array $config = [],
    ) {
    }

    /**
     * @throws EntraException
     */
    public function validateRequest(array $data): void
    {
        if (($data['grant_type'] ?? null) !== self::GRANT_TYPE) {
            throw new EntraException(error: 'unsupported_grant_type', description: 'Use the token-exchange grant');
        }

        // Token-type URIs are public protocol identifiers, not bearer-token secrets.
        // @mago-expect lint:no-insecure-comparison(2)
        if (
            ($data['subject_token_type'] ?? null) !== self::ACCESS_TOKEN
            || ($data['requested_token_type'] ?? self::ACCESS_TOKEN) !== self::ACCESS_TOKEN
            || ($data['actor_token'] ?? null) !== null
            || ($data['resource'] ?? null) !== null
            || ($data['audience'] ?? null) !== null
        ) {
            throw new EntraException(
                error: 'invalid_request',
                description: 'Only an Entra access-token exchange is supported',
            );
        }

        foreach (['client_id', 'client_secret', 'subject_token'] as $name) {
            if (!is_string($data[$name] ?? null) || $data[$name] === '') {
                throw new EntraException(error: 'invalid_request', description: 'Missing or invalid ' . $name);
            }
        }
    }

    /**
     * Extra claims cannot override the standard token claims. No refresh token is issued.
     *
     * @throws EntraException
     * @throws \Random\RandomException
     */
    // Preserve the existing parameter contract used by callers and inherited interfaces.
    // @mago-expect lint:excessive-parameter-list(1)
    public function issueToken(
        string $clientId,
        int|string $subject,
        array $scopes,
        string $privateKey,
        int $subjectExpires,
        array $claims = [],
    ): IssuedAccessToken {
        if ($privateKey === '') {
            throw new EntraException('temporarily_unavailable', 'Client signing key is not configured', 503);
        }
        $now = time();
        $lifetime = min(3600, max(1, (int) ($this->config['access_lifetime'] ?? 3600)), $subjectExpires - $now);
        if ($lifetime <= 0) {
            throw new EntraException('invalid_grant', 'Entra access token has expired');
        }
        $id = bin2hex(random_bytes(32));
        $scope = implode(' ', \Jield\Stdlib\Value::scalars($scopes));
        $token = new Jwt()->encode(
            payload: [
                'id' => $id,
                'jti' => $id,
                'iss' => $this->config['options']['issuer'] ?? 'api-tools',
                'aud' => $clientId,
                'sub' => $subject,
                'iat' => $now,
                'exp' => $now + $lifetime,
                'scope' => $scope,
            ] + $claims,
            key: $privateKey,
            algo: 'RS256',
        );

        return new IssuedAccessToken($token, $now, $now + $lifetime, $scope);
    }
}
