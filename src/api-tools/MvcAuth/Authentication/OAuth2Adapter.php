<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Authentication;

use Jield\ApiTools\MvcAuth\Identity;
use Jield\ApiTools\MvcAuth\MvcAuthEvent;
use Jield\Http\Request;
use Jield\Http\Response;
use Jield\Http\Header\ContentType as ContentTypeHeader;
use OAuth2\Request as OAuth2Request;
use OAuth2\Response as OAuth2Response;
use OAuth2\Server as OAuth2Server;
use Override;
use function in_array;
use function is_array;
use function is_string;
use function method_exists;

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
    private array $requestsWithoutBodies
        = [
            'GET',
            'HEAD',
            'OPTIONS',
        ];

    /** @psalm-param null|string|string[] $types */
    public function __construct(private readonly OAuth2Server $oauth2Server, $types = null)
    {
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
     *
     * @param string $type
     * @return bool
     */
    #[Override]
    public function matches(string $type): bool
    {
        return in_array(needle: $type, haystack: $this->providesTypes, strict: true);
    }

    /**
     * Determine if the given request is a type (oauth2) that we recognize
     *
     */
    #[Override]
    public function getTypeFromRequest(Request $request): false|string
    {
        $type = parent::getTypeFromRequest(request: $request);

        if (false !== $type) {
            return 'oauth2';
        }

        $contentTypeHeader = $request->getHeaders()->has(name: 'Content-Type')
            ? $request->getHeaders()->get(name: 'Content-Type')
            : null;

        if (
            !in_array(needle: $request->getMethod(), haystack: $this->requestsWithoutBodies)
            && $contentTypeHeader instanceof ContentTypeHeader
            && $contentTypeHeader->match(matchAgainst: 'application/x-www-form-urlencoded')
            && $request->getPost(name: 'access_token')
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
     */
    #[Override]
    public function authenticate(Request $request, Response $response, MvcAuthEvent $mvcAuthEvent): Identity\IdentityInterface|\Jield\Http\PhpEnvironment\Response
    {
        $oauth2request = new OAuth2Request(
            query: $request->getQuery()->toArray(),
            request: $request->getPost()->toArray(),
            attributes: [],
            cookies: $request->getCookie() ? $request->getCookie()->getArrayCopy() : [],
            files: $request->getFiles() ? $request->getFiles()->toArray() : [],
            server: method_exists(object_or_class: $request, method: 'getServer') ? $request->getServer()->toArray() : $_SERVER,
            content: $request->getContent(),
            headers: $request->getHeaders()->toArray()
        );

        $token = $this->oauth2Server->getAccessTokenData(request: $oauth2request);

        // Failure to validate
        if (!$token) {
            return $this->processInvalidToken(response: $response);
        }

        $identity = new Identity\AuthenticatedIdentity(identity: $token);
        $identity->setName(name: 'user_' . $token['user_id']);

        return $identity;
    }

    /**
     * Handle a invalid Token.
     *
     */
    private function processInvalidToken(Response $response): Response|Identity\GuestIdentity
    {
        $oauth2Response = $this->oauth2Server->getResponse();
        $status         = $oauth2Response->getStatusCode();

        // 401 or 403 mean invalid credentials or unauthorized scopes; report those.
        if (in_array(needle: $status, haystack: [401, 403], strict: true) && null !== $oauth2Response->getParameter(name: 'error')) {
            return $this->mergeOAuth2Response(status: $status, response: $response, oauth2Response: $oauth2Response);
        }

        // Merge in any headers; typically sets a WWW-Authenticate header.
        $this->mergeOAuth2ResponseHeaders(response: $response, oauth2Headers: $oauth2Response->getHttpHeaders());

        // Otherwise, no credentials were present at all, so we just return a guest identity.
        return new Identity\GuestIdentity();
    }

    /**
     * Merge the OAuth2\Response instance's status and headers into the current Jield\Http\Response.
     *
     * @param int $status
     */
    private function mergeOAuth2Response(int $status, Response $response, OAuth2Response $oauth2Response): Response
    {
        $response->setStatusCode(code: $status);
        return $this->mergeOAuth2ResponseHeaders(response: $response, oauth2Headers: $oauth2Response->getHttpHeaders());
    }

    /**
     * Merge the OAuth2\Response headers into the current Jield\Http\Response.
     *
     */
    private function mergeOAuth2ResponseHeaders(Response $response, array $oauth2Headers): Response
    {
        if ($oauth2Headers === []) {
            return $response;
        }

        $headers = $response->getHeaders();
        foreach ($oauth2Headers as $header => $value) {
            $headers->addHeaderLine(headerFieldNameOrLine: $header, fieldValue: $value);
        }

        return $response;
    }
}
