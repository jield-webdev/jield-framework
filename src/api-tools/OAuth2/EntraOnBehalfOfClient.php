<?php

declare(strict_types=1);

namespace Jield\ApiTools\OAuth2;

use GuzzleHttp\ClientInterface;
use Throwable;

final readonly class EntraOnBehalfOfClient
{
    public function __construct(
        private ClientInterface $http,
    ) {}

    /** Call only after the middle tier has authenticated an access token intended for itself. */
    public function acquireToken(EntraClientInterface $middleTier, string $assertion, string $scope): string
    {
        if (
            !$middleTier->serviceIsMicrosoft()
            || $middleTier->getClientSecret() === ''
            || !$middleTier->getAccessTokenUrl()
            || preg_match(
                pattern: '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D',
                subject: $middleTier->getTenantId() ?? '',
            ) !== 1
            || $assertion === ''
            || trim($scope) === ''
        ) {
            throw new EntraException(
                error: 'invalid_request',
                description: 'Microsoft middle-tier credentials, assertion and scope are required',
            );
        }

        try {
            $response = $this->http->request(method: 'POST', uri: \Jield\Stdlib\Value::instanceOrString($middleTier->getAccessTokenUrl(), \Psr\Http\Message\UriInterface::class), options: [
                'form_params' => [
                    'client_id' => $middleTier->getClientId(),
                    'client_secret' => $middleTier->getClientSecret(),
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion,
                    'requested_token_use' => 'on_behalf_of',
                    'scope' => $scope,
                ],
                'timeout' => 10,
                'connect_timeout' => 3,
                'allow_redirects' => false,
                'http_errors' => false,
            ]);
            /** @var mixed $result */
            $result = json_decode(
                json: (string) $response->getBody(),
                associative: true,
                depth: 32,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (Throwable) {
            throw new EntraException(
                error: 'temporarily_unavailable',
                description: 'Entra token endpoint is unavailable',
                status: 503,
            );
        }

        if (
            $response->getStatusCode() !== 200
            || !is_string($result['access_token'] ?? null)
            || $result['access_token'] === ''
        ) {
            // Avoid returning provider diagnostics that could include credentials or assertions.
            throw new EntraException(
                error: 'invalid_grant',
                description: 'Entra refused the OBO request; check consent and Conditional Access',
            );
        }
        return $result['access_token'];
    }
}
