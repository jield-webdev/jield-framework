<?php

declare(strict_types=1);

namespace JieldTest\ApiTools\OAuth2;

use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Jield\ApiTools\OAuth2\EntraException;
use Jield\ApiTools\OAuth2\EntraJwksProvider;
use Jield\ApiTools\OAuth2\EntraResourceInterface;
use Jield\ApiTools\OAuth2\EntraTokenValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class EntraTokenValidatorTest extends TestCase
{
    private const string TENANT = '11111111-1111-4111-8111-111111111111';
    private const string API = '22222222-2222-4222-8222-222222222222';
    private const string CALLER = '33333333-3333-4333-8333-333333333333';

    private static string $privateKey;

    /** @var array<string, string> */
    private static array $jwk;

    public static function setUpBeforeClass(): void
    {
        $randomState = tempnam(sys_get_temp_dir(), 'jield-openssl-');
        self::assertNotFalse($randomState);
        $previousRandomState = getenv('RANDFILE');
        putenv('RANDFILE=' . $randomState);
        try {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        } finally {
            putenv($previousRandomState === false ? 'RANDFILE' : 'RANDFILE=' . $previousRandomState);
            unlink($randomState);
        }
        self::assertNotFalse($key);
        $privateKey = '';
        self::assertTrue(openssl_pkey_export($key, $privateKey));
        self::$privateKey = $privateKey;
        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);
        self::$jwk = [
            'kid' => 'regression-key',
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'issuer' => 'https://login.microsoftonline.com/{tenantid}/v2.0',
            'n' => JWT::urlsafeB64Encode($details['rsa']['n']),
            'e' => JWT::urlsafeB64Encode($details['rsa']['e']),
        ];
    }

    public function testAcceptsSignedDelegatedTokenWithValidClaims(): void
    {
        $claims = $this->claims();
        self::assertSame($claims, $this->validator()->validate($this->token($claims), $this->resource()));
    }

    public function testAcceptsAllowedApplicationRoleWithoutDelegatedScopes(): void
    {
        $claims = $this->claims();
        unset($claims['scp']);
        $claims['idtyp'] = 'app';
        $claims['roles'] = ['Api.Read.All'];
        self::assertSame($claims, $this->validator()->validate($this->token($claims), $this->resource()));
    }

    /** @param array<string, mixed> $changes */
    #[DataProvider('invalidClaims')]
    public function testRejectsInvalidClaims(array $changes): void
    {
        $claims = array_replace($this->claims(), $changes);
        $this->expectException(EntraException::class);
        $this->validator()->validate($this->token($claims), $this->resource());
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidClaims(): iterable
    {
        yield 'version' => [['ver' => '1.0']];
        yield 'issuer' => [['iss' => 'https://example.com/issuer']];
        yield 'tenant' => [['tid' => self::CALLER]];
        yield 'audience' => [['aud' => self::CALLER]];
        yield 'object ID' => [['oid' => 'not-a-guid']];
        yield 'caller' => [['azp' => self::API]];
        yield 'missing scope' => [['scp' => null]];
        yield 'empty scope' => [['scp' => '']];
        yield 'application with delegated scope' => [['idtyp' => 'app', 'roles' => ['Api.Read.All']]];
        yield 'application with wrong role' => [['idtyp' => 'app', 'scp' => null, 'roles' => ['Other.Role']]];
    }

    private function validator(): EntraTokenValidator
    {
        $http = new Client([
            'handler' => HandlerStack::create(new MockHandler([
                new Response(
                    200,
                    ['Content-Type' => 'application/json'],
                    json_encode(['keys' => [self::$jwk]], JSON_THROW_ON_ERROR),
                ),
            ])),
        ]);

        return new EntraTokenValidator(new EntraJwksProvider($http, new ArrayAdapter()), ['Api.Read.All']);
    }

    /** @return array<string, mixed> */
    private function claims(): array
    {
        return [
            'ver' => '2.0',
            'iss' => 'https://login.microsoftonline.com/' . self::TENANT . '/v2.0',
            'tid' => self::TENANT,
            'aud' => self::API,
            'oid' => '44444444-4444-4444-8444-444444444444',
            'exp' => time() + 300,
            'nbf' => time() - 30,
            'iat' => time() - 30,
            'azp' => self::CALLER,
            'scp' => 'access_as_user',
        ];
    }

    /** @param array<string, mixed> $claims */
    private function token(array $claims): string
    {
        return JWT::encode($claims, self::$privateKey, 'RS256', 'regression-key');
    }

    private function resource(): EntraResourceInterface
    {
        return new class (self::TENANT, self::API, self::CALLER) implements EntraResourceInterface {
            public function __construct(private string $tenant, private string $api, private string $caller)
            {
            }

            public function serviceIsMicrosoft(): bool
            {
                return true;
            }

            public function isEntraTokenExchangeEnabled(): bool
            {
                return true;
            }

            public function getTenantId(): string
            {
                return $this->tenant;
            }

            public function getClientId(): string
            {
                return $this->api;
            }

            public function getEntraAllowedClientIds(): string
            {
                return $this->caller;
            }
        };
    }
}
