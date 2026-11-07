<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Authentication;

use Jield\ApiTools\MvcAuth\Identity;
use Jield\ApiTools\MvcAuth\MvcAuthEvent;
use Jield\Stdlib\Value;
use Laminas\Http\Header\ContentType as ContentTypeHeader;
use Laminas\Http\Header\Cookie;
use Laminas\Http\Headers;
use Laminas\Http\Request;
use Laminas\Http\Response;
use OAuth2\Request as OAuth2Request;
use OAuth2\Response as OAuth2Response;
use OAuth2\Server as OAuth2Server;
use Override;
use Throwable;
use Webmozart\Assert\Assert;

use function in_array;
use function is_array;
use function is_string;
use function method_exists;

// Preserve the existing public API and the state and operations that implement it.
// @mago-expect lint:cyclomatic-complexity(1)
class OAuth2Adapter extends AbstractAdapter
{
    /**
     * Authorization header token types this adapter can fulfill.
     */
    protected array $authorizationTokenTypes = ['bearer'];

    /**
     * Authentication types this adapter provides.
     */
    private array $providesTypes = ['oauth2'];

    /**
     * Request methods that will not have request bodies
     */
    private array $requestsWithoutBodies = [
        'GET',
        'HEAD',
        'OPTIONS',
    ];

    /** @psalm-param null|string|string[] $types */
    public function __construct(
        private readonly OAuth2Server $oauth2Server,
        $types = null,
    ) {
        if (is_string(value: $types) && ($types !== '' && $types !== '0')) {
            $types = [$types];
        }

        if (is_array(value: $types)) {
            $this->providesTypes = $types;
        }
    }

    /**
     * @return array Array of types this adapter can handle.
     */
    #[Override]
    public function provides(): array
    {
        return $this->providesTypes;
    }

    /**
     * Attempt to match a requested authentication type
     * against what the adapter provides.
     */
    #[Override]
    public function matches(string $type): bool
    {
        return in_array(needle: $type, haystack: $this->providesTypes, strict: true);
    }

    /**
     * Determine if the given request is a type (oauth2) that we recognize
     */
    #[Override]
    public function getTypeFromRequest(Request $request): false|string
    {
        $type = parent::getTypeFromRequest(request: $request);

        if (false !== $type) {
            return 'oauth2';
        }

        $contentTypeHeader = Assert::isInstanceOf($request->getHeaders(), Headers::class)->has('Content-Type')
            ? Assert::isInstanceOf($request->getHeaders(), Headers::class)->get('Content-Type')
            : null;

        if (
            !in_array(needle: $request->getMethod(), haystack: $this->requestsWithoutBodies, strict: true)
            && $contentTypeHeader instanceof ContentTypeHeader
            && $contentTypeHeader->match('application/x-www-form-urlencoded')
            && Value::truthy($request->getPost(name: 'access_token'))
        ) {
            return 'oauth2';
        }

        if (null !== $request->getQuery(name: 'access_token')) {
            return 'oauth2';
        }

        return false;
    }

    /**
     * Perform pre-flight authentication operations.
     *
     * Performs a no-op; nothing needs to happen for this adapter.
     *
     * @return void
     */
    #[Override]
    public function preAuth(Request $request, Response $response)
    {
    }

    /**
     * Attempt to authenticate the current request.
     *
     * @throws Throwable
     */
    #[Override]
    public function authenticate(
        Request $request,
        Response $response,
        MvcAuthEvent $mvcAuthEvent,
    ): Identity\IdentityInterface|Response {
        $oauth2request = new OAuth2Request(
            query: Assert::isArray(Value::call($request->getQuery(), 'toArray')),
            request: Assert::isArray(Value::call($request->getPost(), 'toArray')),
            attributes: [],
            cookies: ($cookie = $request->getCookie()) instanceof Cookie ? $cookie->getArrayCopy() : [],
            files: Assert::isArray($request->getFiles()->toArray()),
            server: Assert::isArray(
                method_exists(object_or_class: $request, method: 'getServer')
                    ? Value::call($request->getServer(), 'toArray')
                    : $_SERVER,
            ),
            content: Assert::nullOrString($request->getContent()),
            headers: Assert::isInstanceOf($request->getHeaders(), Headers::class)->toArray(),
        );

        try {
            /** @mago-var mixed $token */
            $token = $this->oauth2Server->getAccessTokenData(request: $oauth2request);
        } catch (Throwable $exception) {
            // The OAuth2 JWT decoder can raise an OpenSSL error for a missing or malformed public key.
            if (!str_starts_with($exception->getMessage(), 'openssl_verify():')) {
                throw $exception;
            }

            $response->setStatusCode(401);
            $response->getHeaders()->addHeaderLine('Content-Type', 'application/json');
            $response->getHeaders()->addHeaderLine('Cache-Control', 'no-store');
            $response->getHeaders()->addHeaderLine('WWW-Authenticate', 'Bearer error="invalid_token"');
            $response->setContent(json_encode([
                'error' => 'invalid_token',
                'error_description' => 'The access token provided is invalid',
            ], JSON_THROW_ON_ERROR));

            return $response;
        }

        // Failure to validate
        if (!$token) {
            return $this->processInvalidToken(response: $response);
        }

        $identity = new Identity\AuthenticatedIdentity(identity: Assert::isArray($token));
        $identity->setName(name: 'user_' . Value::stringify($token['user_id']));

        return $identity;
    }

    /**
     * Handle a invalid Token.
     *
     * @throws \ReflectionException
     */
    private function processInvalidToken(Response $response): Response|Identity\GuestIdentity
    {
        /** @mago-var mixed $oauth2Response */
        $oauth2Response = $this->oauth2Server->getResponse();
        /** @mago-var mixed $status */
        $status = Value::call($oauth2Response, 'getStatusCode');

        // 401 or 403 mean invalid credentials or unauthorized scopes; report those.
        if (
            in_array(needle: $status, haystack: [401, 403], strict: true)
            && null !== Value::call($oauth2Response, 'getParameter', ['name' => 'error'])
        ) {
            return $this->mergeOAuth2Response(
                status: $status,
                response: $response,
                oauth2Response: Assert::isInstanceOf($oauth2Response, \OAuth2\Response::class),
            );
        }

        // Merge in any headers; typically sets a WWW-Authenticate header.
        $this->mergeOAuth2ResponseHeaders(
            response: $response,
            oauth2Headers: Assert::isArray(Value::call($oauth2Response, 'getHttpHeaders')),
        );

        // Otherwise, no credentials were present at all, so we just return a guest identity.
        return new Identity\GuestIdentity();
    }

    /**
     * Merge the OAuth2\Response instance's status and headers into the current Laminas\Http\Response.
     */
    private function mergeOAuth2Response(int $status, Response $response, OAuth2Response $oauth2Response): Response
    {
        $response->setStatusCode(code: $status);
        return $this->mergeOAuth2ResponseHeaders(response: $response, oauth2Headers: $oauth2Response->getHttpHeaders());
    }

    /**
     * Merge the OAuth2\Response headers into the current Laminas\Http\Response.
     */
    private function mergeOAuth2ResponseHeaders(Response $response, array $oauth2Headers): Response
    {
        if ($oauth2Headers === []) {
            return $response;
        }

        $headers = $response->getHeaders();
        /** @mago-var mixed $value */
        foreach ($oauth2Headers as $header => $value) {
            $headers->addHeaderLine(
                headerFieldNameOrLine: Assert::string($header),
                fieldValue: Assert::nullOrString($value),
            );
        }

        return $response;
    }
}
