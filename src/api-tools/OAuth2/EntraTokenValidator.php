<?php

declare(strict_types=1);

namespace Jield\ApiTools\OAuth2;

use Firebase\JWT\JWT;
use SensitiveParameter;
use Throwable;
use Webmozart\Assert\Assert;

class EntraTokenValidator
{
    /** @param list<string> $applicationRoles Application roles accepted by the consuming app. */
    public function __construct(
        private readonly EntraJwksProvider $keys,
        private readonly array $applicationRoles = [],
    ) {}

    public function validate(#[SensitiveParameter] string $token, EntraResourceInterface $service): array
    {
        $tenantId = $service->getTenantId();
        $clientId = $service->getClientId();
        $configurationError = 'Entra token exchange is not configured';

        if (!$service->serviceIsMicrosoft()) {
            $this->invalid(standardMessage: $configurationError, detail: 'The OAuth service type must be Microsoft');
        }

        if (!$service->isEntraTokenExchangeEnabled()) {
            $this->invalid(
                standardMessage: $configurationError,
                detail: 'Entra token exchange is disabled for this OAuth service',
            );
        }

        if (!self::isGuid($tenantId)) {
            $this->invalid(
                standardMessage: $configurationError,
                detail: 'The configured tenant ID must be a GUID; got ' . self::describe($tenantId),
            );
        }

        if (!self::isGuid($clientId)) {
            $this->invalid(
                standardMessage: $configurationError,
                detail: 'The configured API client ID must be a GUID; got ' . self::describe($clientId),
            );
        }

        $tokenError = 'Invalid Entra access token';
        if (strlen($token) > 32_768) {
            $this->invalid(standardMessage: $tokenError, detail: 'JWT exceeds the 32768 byte limit');
        }

        $segments = explode(separator: '.', string: $token);
        if (count($segments) !== 3) {
            $this->invalid(
                standardMessage: $tokenError,
                detail: 'JWT must contain exactly three dot-separated segments; got ' . count($segments),
            );
        }

        try {
            $header = json_decode(
                json: JWT::urlsafeB64Decode($segments[0]),
                associative: true,
                depth: 16,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (Throwable $exception) {
            $this->invalid(
                standardMessage: $tokenError,
                detail: 'JWT header cannot be decoded: ' . $exception->getMessage(),
            );
        }
        if (!is_array($header)) {
            $this->invalid(
                standardMessage: 'Invalid Entra access token header',
                detail: 'JWT header must be a JSON object',
            );
        }

        $headerError = 'Invalid Entra access token header';
        if (($header['alg'] ?? null) !== 'RS256') {
            $this->invalid(
                standardMessage: $headerError,
                detail: 'Header alg must be RS256; got ' . self::describeClaim(claims: $header, name: 'alg'),
            );
        }
        if (!is_string($header['kid'] ?? null) || $header['kid'] === '' || strlen($header['kid']) > 128) {
            $this->invalid(
                standardMessage: $headerError,
                detail: 'Header kid must be a nonempty string of at most 128 bytes; got '
                    . self::describeClaim(claims: $header, name: 'kid'),
            );
        }
        if (isset($header['crit'])) {
            $this->invalid(
                standardMessage: $headerError,
                detail: 'Unsupported critical header parameter crit is present',
            );
        }

        try {
            $key = $this->keys->getKey(tenantId: Assert::string($tenantId), kid: $header['kid']);
        } catch (EntraException $exception) {
            $this->signingKeyFailure(exception: $exception, kid: $header['kid']);
        }
        try {
            $claims = get_object_vars(JWT::decode(jwt: $token, keyOrKeyArray: $key));
        } catch (Throwable $exception) {
            $this->invalid(
                standardMessage: 'Invalid or expired Entra access token',
                detail: 'JWT verification failed (' . $exception::class . '): ' . $exception->getMessage(),
            );
        }

        $claimsError = 'Entra token claims do not match the configured API, tenant or caller';
        if (($claims['ver'] ?? null) !== '2.0') {
            $this->invalid(
                standardMessage: $claimsError,
                detail: 'Claim ver must be "2.0"; got ' . self::describeClaim(claims: $claims, name: 'ver'),
            );
        }
        $expectedIssuer = 'https://login.microsoftonline.com/' . $tenantId . '/v2.0';

        if (($claims['iss'] ?? null) !== $expectedIssuer) {
            $this->invalid(
                standardMessage: $claimsError,
                detail: 'Claim iss must be ' . self::describe($expectedIssuer) . '; got '
                    . self::describeClaim(claims: $claims, name: 'iss'),
            );
        }
        if (($claims['tid'] ?? null) !== $tenantId) {
            $this->invalid(
                standardMessage: $claimsError,
                detail: 'Claim tid must be ' . self::describe($tenantId) . '; got '
                    . self::describeClaim(claims: $claims, name: 'tid'),
            );
        }
        if (($claims['aud'] ?? null) !== $clientId) {
            $this->invalid(
                standardMessage: $claimsError,
                detail: 'Claim aud must be ' . self::describe($clientId) . '; got '
                    . self::describeClaim(claims: $claims, name: 'aud'),
            );
        }
        if (!self::isGuid($claims['oid'] ?? null)) {
            $this->invalid(
                standardMessage: $claimsError,
                detail: 'Claim oid must be a GUID; got ' . self::describeClaim(claims: $claims, name: 'oid'),
            );
        }

        $now = time();
        if (!is_int($claims['exp'] ?? null) || $claims['exp'] <= $now) {
            $this->invalid(
                standardMessage: $claimsError,
                detail: 'Claim exp must be an integer after current Unix time ' . $now . '; got '
                    . self::describeClaim(claims: $claims, name: 'exp'),
            );
        }

        if (!is_int($claims['nbf'] ?? null) || $claims['nbf'] > $now) {
            $this->invalid(
                standardMessage: $claimsError,
                detail: 'Claim nbf must be an integer no later than current Unix time ' . $now . '; got '
                    . self::describeClaim(claims: $claims, name: 'nbf'),
            );
        }

        if (!is_int($claims['iat'] ?? null) || $claims['iat'] > $now) {
            $this->invalid(
                standardMessage: $claimsError,
                detail: 'Claim iat must be an integer no later than current Unix time ' . $now . '; got '
                    . self::describeClaim(claims: $claims, name: 'iat'),
            );
        }

        if (!is_string($claims['azp'] ?? null)) {
            $this->invalid(
                standardMessage: $claimsError,
                detail: 'Claim azp must be a configured caller client ID; got '
                    . self::describeClaim(claims: $claims, name: 'azp'),
            );
        }

        $allowedCallers = Scope::split($service->getEntraAllowedClientIds());
        if (!in_array(needle: $claims['azp'], haystack: $allowedCallers, strict: true)) {
            $this->invalid(
                standardMessage: $claimsError,
                detail: 'Claim azp ' . self::describe($claims['azp']) . ' is not in the configured caller client IDs '
                    . self::describe($allowedCallers),
            );
        }

        if (($claims['idtyp'] ?? null) === 'app') {
            // idtyp is required for application authorization by caller ID. A user token
            // with roles must never be mistaken for a service principal or an ID token.
            $applicationError = 'Application tokens require idtyp app, no delegated scopes and an allowed application role';
            if (array_key_exists(key: 'scp', array: $claims)) {
                $this->invalid(
                    standardMessage: $applicationError,
                    detail: 'Application token must omit claim scp; got '
                        . self::describeClaim(claims: $claims, name: 'scp'),
                );
            }
            if (
                !is_array($claims['roles'] ?? null)
                || !array_is_list($claims['roles'])
                || array_filter(array: $claims['roles'], callback: static fn(mixed $role): bool => !is_string($role))
                    !== []
            ) {
                $this->invalid(
                    standardMessage: $applicationError,
                    detail: 'Claim roles must be a list of strings; got '
                        . self::describeClaim(claims: $claims, name: 'roles'),
                );
            }
            if (array_intersect($this->applicationRoles, $claims['roles']) === []) {
                $this->invalid(
                    standardMessage: $applicationError,
                    detail: 'Claim roles must contain ' . implode(' or ', $this->applicationRoles) . '; got '
                        . self::describeClaim(claims: $claims, name: 'roles'),
                );
            }
        } elseif (!is_string($claims['scp'] ?? null) || trim($claims['scp']) === '') {
            $this->invalid(
                standardMessage: 'A delegated scope or an explicitly identified application token is required',
                detail: 'Token has no nonempty scp claim and idtyp is not "app"; got scp '
                    . self::describeClaim(claims: $claims, name: 'scp')
                    . ' and idtyp '
                    . self::describeClaim(claims: $claims, name: 'idtyp'),
            );
        }

        return $claims;
    }

    protected function invalid(string $standardMessage, string $detail): never
    {
        throw new EntraException(error: 'invalid_grant', description: $standardMessage);
    }

    private static function isGuid(mixed $value): bool
    {
        return (
            is_string($value)
            && preg_match(pattern: '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', subject: $value)
            === 1
        );
    }

    private static function describe(mixed $value): string
    {
        $encoded = json_encode(
            value: $value,
            flags: JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );
        if (!is_string($encoded)) {
            return get_debug_type($value);
        }

        return strlen($encoded) > 160 ? substr(string: $encoded, offset: 0, length: 160) . '…' : $encoded;
    }

    private static function describeClaim(array $claims, string $name): string
    {
        return array_key_exists(key: $name, array: $claims) ? self::describe($claims[$name]) : '<missing>';
    }

    protected function signingKeyFailure(EntraException $exception, string $kid): never
    {
        throw $exception;
    }
}
