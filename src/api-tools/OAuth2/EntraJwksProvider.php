<?php

declare(strict_types=1);

namespace Jield\ApiTools\OAuth2;

use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use GuzzleHttp\ClientInterface;
use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;
use Throwable;

readonly class EntraJwksProvider
{
    public function __construct(
        private ClientInterface $http,
        private CacheItemPoolInterface $cache,
    ) {}

    /**
     * @throws EntraException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getKey(string $tenantId, string $kid): Key
    {
        // The URL is derived exclusively from a validated database tenant ID, never from JWT headers.
        $item = $this->cache->getItem('entra_jwks_' . $tenantId);
        /** @mago-var mixed $data */
        $data = $item->isHit() ? $item->get() : null;

        $key = is_array($data) ? $this->findKey(keys: \Webmozart\Assert\Assert::isArray($data['keys'] ?? []), tenantId: $tenantId, kid: $kid) : null;
        if ($key instanceof Key) {
            return $key;
        }

        // Refresh on an unknown kid to handle rollover, at most once per minute per tenant.
        if (is_array($data) && is_int($data['fetchedAt'] ?? null) && $data['fetchedAt'] > (time() - 60)) {
            throw new EntraException(error: 'invalid_grant', description: 'Unknown Entra signing key');
        }

        try {
            $response = $this->http->request(
                method: 'GET',
                uri: 'https://login.microsoftonline.com/' . $tenantId . '/discovery/v2.0/keys',
                options: [
                    'timeout' => 5,
                    'connect_timeout' => 3,
                    'allow_redirects' => false,
                ],
            );
            if ($response->getStatusCode() !== 200 || ($response->getBody()->getSize() ?? 0) > 1_048_576) {
                throw new RuntimeException('Invalid JWKS response');
            }
            /** @mago-var mixed $document */
            $document = json_decode(
                json: (string) $response->getBody(),
                associative: true,
                depth: 32,
                flags: JSON_THROW_ON_ERROR,
            );
            if (!is_array($document['keys'] ?? null)) {
                throw new RuntimeException('Invalid JWKS document');
            }
            $item->set(['keys' => $document['keys'], 'fetchedAt' => time()])->expiresAfter(3600);
            $this->cache->save($item);
        } catch (Throwable) {
            throw new EntraException(
                error: 'temporarily_unavailable',
                description: 'Entra signing keys are unavailable',
                status: 503,
            );
        }

        return (
            $this->findKey(keys: $document['keys'], tenantId: $tenantId, kid: $kid) ?? throw new EntraException(
                error: 'invalid_grant',
                description: 'Unknown Entra signing key',
            )
        );
    }

    /**
     * @throws EntraException
     */
    private function findKey(array $keys, string $tenantId, string $kid): ?Key
    {
        $issuer = 'https://login.microsoftonline.com/' . $tenantId . '/v2.0';
        /** @mago-var mixed $key */
        foreach ($keys as $key) {
            if (!is_array($key) || ($key['kid'] ?? null) !== $kid) {
                continue;
            }
            /** @mago-var mixed $keyIssuer */
            $keyIssuer = $key['issuer'] ?? '';
            if (
                !is_string($keyIssuer)
                || str_replace(search: '{tenantid}', replace: $tenantId, subject: $keyIssuer) !== $issuer
                || ($key['kty'] ?? null) !== 'RSA'
                || ($key['use'] ?? null) !== 'sig'
                || ($key['alg'] ?? 'RS256') !== 'RS256'
            ) {
                throw new EntraException(error: 'invalid_grant', description: 'Invalid Entra signing key');
            }

            try {
                return JWK::parseKey(jwk: $key, defaultAlg: 'RS256');
            } catch (Throwable) {
                throw new EntraException(error: 'invalid_grant', description: 'Invalid Entra signing key');
            }
        }

        return null;
    }
}
