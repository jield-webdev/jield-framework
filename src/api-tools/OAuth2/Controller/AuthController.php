<?php

declare(strict_types=1);

namespace Jield\ApiTools\OAuth2\Controller;

use Jield\ApiTools\ApiProblem\ApiProblem;
use Jield\ApiTools\ApiProblem\ApiProblemResponse;
use Jield\ApiTools\ApiProblem\Exception\ProblemExceptionInterface;
use Jield\ApiTools\ContentNegotiation\ControllerPlugin\BodyParams;
use Jield\ApiTools\ContentNegotiation\ViewModel;
use Jield\ApiTools\OAuth2\Provider\UserId\UserIdProviderInterface;
use Laminas\Http\PhpEnvironment\Request as PhpEnvironmentRequest;
use Laminas\Http\Request as HttpRequest;
use Laminas\Http\Response;
use Laminas\Http\Header\HeaderInterface;
use Jield\Mvc\Controller\AbstractActionController;
use Laminas\Stdlib\ResponseInterface;
use OAuth2\Request as OAuth2Request;
use OAuth2\Response as OAuth2Response;
use OAuth2\Server as OAuth2Server;
use RuntimeException;
use Webmozart\Assert\Assert;
use function json_encode;
use function sprintf;

/**
 * @method BodyParams bodyParams()
 */
class AuthController extends AbstractActionController
{
    protected bool $apiProblemErrorResponse = true;

    protected ?OAuth2Server $server = null;

    /** @var callable Factory for generating an OAuth2Server instance. */
    protected $serverFactory;

    /**
     * Constructor
     */
    public function __construct(callable $serverFactory, protected UserIdProviderInterface $userIdProvider)
    {
        $this->serverFactory = $serverFactory;
    }

    /**
     * Should the controller return ApiProblemResponse?
     *
     * @return bool
     */
    public function isApiProblemErrorResponse(): bool
    {
        return $this->apiProblemErrorResponse;
    }

    /**
     * Indicate whether ApiProblemResponse or oauth2 errors should be returned.
     *
     * Boolean true indicates ApiProblemResponse should be returned (the
     * default), while false indicates oauth2 errors (per the oauth2 spec)
     * should be returned.
     *
     * @param bool $apiProblemErrorResponse
     * @return void
     */
    public function setApiProblemErrorResponse(bool $apiProblemErrorResponse): void
    {
        $this->apiProblemErrorResponse = $apiProblemErrorResponse;
    }

    /**
     * Token Action (/oauth)
     */
    public function tokenAction(): ?Response
    {
        $request = $this->getRequest();
        if (!$request instanceof HttpRequest) {
            // not an HTTP request; nothing left to do
            return null;
        }

        if ($request->isOptions()) {
            // OPTIONS request.
            // This is most likely a CORS attempt; as such, pass the response on.
            return $this->getResponse();
        }

        $oauth2request = $this->getOAuth2Request();
        $oauth2server  = $this->getOAuth2Server(type: $this->params('oauth'));
        try {
            $response = $oauth2server->handleTokenRequest(request: $oauth2request);
        } catch (ProblemExceptionInterface $problemException) {
            $status = $problemException->getCode() ?: 401;
            $status = $status >= 400 && $status < 600 ? $status : 401;

            return new ApiProblemResponse(
                apiProblem: new ApiProblem(status: $status, detail: $problemException)
            );
        }

        Assert::isInstanceOf(value: $response, class: OAuth2Response::class, message: 'Did not receive OAuth2 response instance');

        if ($response->isClientError()) {
            return $this->getErrorResponse(response: $response);
        }

        return $this->setHttpResponse(response: $response);
    }

    /**
     * Token Revoke (/oauth/revoke)
     */
    public function revokeAction(): ?Response
    {
        $request = $this->getRequest();
        if (!$request instanceof HttpRequest) {
            // not an HTTP request; nothing left to do
            return null;
        }

        if ($request->isOptions()) {
            // OPTIONS request.
            // This is most likely a CORS attempt; as such, pass the response on.
            return $this->getResponse();
        }

        $oauth2request = $this->getOAuth2Request();
        $response      = $this->getOAuth2Server(type: $this->params('oauth'))->handleRevokeRequest(request: $oauth2request);
        Assert::isInstanceOf(value: $response, class: OAuth2Response::class, message: 'Did not receive OAuth2 response instance');

        if ($response->isClientError()) {
            return $this->getErrorResponse(response: $response);
        }

        return $this->setHttpResponse(response: $response);
    }

    /**
     * Test resource (/oauth/resource)
     */
    public function resourceAction(): Response
    {
        $server = $this->getOAuth2Server(type: $this->params('oauth'));

        // Handle a request for an OAuth2.0 Access Token and send the response to the client
        if (!$server->verifyResourceRequest(request: $this->getOAuth2Request())) {
            $response = $server->getResponse();
            Assert::isInstanceOf(
                value: $response,
                class: OAuth2Response::class,
                message: 'Did not receive valid OAuth2 response instance from OAuth2 Server'
            );
            return $this->getApiProblemResponse(response: $response);
        }

        $httpResponse = $this->getResponse();
        Assert::isInstanceOf(value: $httpResponse, class: Response::class, message: 'Cannot use non-HTTP response instance');
        $httpResponse->setStatusCode(code: 200);
        $httpResponse->getHeaders()->addHeaders(headers: ['Content-type' => 'application/json']);
        $httpResponse->setContent(
            value: json_encode(value: ['success' => true, 'message' => 'You accessed my APIs!'])
        );
        return $httpResponse;
    }

    /**
     * Authorize action (/oauth/authorize)
     */
    public function authorizeAction(): Response|ViewModel|ResponseInterface|ApiProblemResponse
    {
        $serverType = $this->params('oauth');
        Assert::nullOrStringNotEmpty(value: $serverType);

        $this->getOAuth2Server(type: $serverType);
        $request  = $this->getOAuth2Request();
        $response = new OAuth2Response();

        // validate the authorize request
        $isValid = $this->server->validateAuthorizeRequest(request: $request, response: $response);

        if (!$isValid) {
            return $this->getErrorResponse(response: $response);
        }

        $authorized = $request->request(name: 'authorized', default: false);
        if (empty($authorized)) {
            $clientId = $request->query(name: 'client_id', default: false);
            $view     = new ViewModel(variables: ['clientId' => $clientId]);
            $view->setTemplate(template: 'oauth/authorize');
            return $view;
        }

        $isAuthorized   = $authorized === 'yes';
        $userIdProvider = $this->userIdProvider;

        $this->server->handleAuthorizeRequest(
            request: $request,
            response: $response,
            is_authorized: $isAuthorized,
            user_id: $userIdProvider(request: $this->getRequest())
        );

        $redirect = $response->getHttpHeader(name: 'Location');
        if (!empty($redirect)) {
            return $this->redirect()->toUrl(url: $redirect);
        }

        return $this->getErrorResponse(response: $response);
    }

    /**
     * Receive code action prints the code/token access
     */
    public function receiveCodeAction(): ViewModel
    {
        $code = $this->params()->fromQuery(param: 'code', default: false);
        $view = new ViewModel(variables: [
            'code' => $code,
        ]);
        $view->setTemplate(template: 'oauth/receive-code');
        return $view;
    }

    protected function getErrorResponse(OAuth2Response $response): Response|ResponseInterface|ApiProblemResponse
    {
        if ($this->isApiProblemErrorResponse()) {
            return $this->getApiProblemResponse(response: $response);
        }

        return $this->setHttpResponse(response: $response);
    }

    /**
     * Map OAuth2Response to ApiProblemResponse
     *
     */
    protected function getApiProblemResponse(OAuth2Response $response): ApiProblemResponse
    {
        $parameters       = $response->getParameters();
        $errorUri         = $parameters['error_uri'] ?? null;
        $error            = $parameters['error'] ?? null;
        $errorDescription = $parameters['error_description'] ?? null;

        return new ApiProblemResponse(
            apiProblem: new ApiProblem(
                status: $response->getStatusCode(),
                detail: $errorDescription,
                type: $errorUri,
                title: $error
            )
        );
    }

    /**
     * Create an OAuth2 request based on the Jield request object
     *
     * Marshals:
     *
     * - query string
     * - body parameters, via content negotiation
     * - "server", specifically the request method and content type
     * - raw content
     * - headers
     *
     * This ensures that JSON requests providing credentials for OAuth2
     * verification/validation can be processed.
     *
     * @return OAuth2Request
     */
    protected function getOAuth2Request(): OAuth2Request
    {
        $jieldRequest = $this->getRequest();
        Assert::isInstanceOf(value: $jieldRequest, class: HttpRequest::class, message: 'Cannot build OAuth2 request without HTTP request');
        $headers        = $jieldRequest->getHeaders();

        // Marshal content type, so we can seed it into the $_SERVER array
        if ($headers->has(name: 'Content-Type')) {
            $contentTypeHeader = $headers->get(name: 'Content-Type');
            if ($contentTypeHeader instanceof HeaderInterface) {
                $contentTypeHeader->getFieldValue();
            }
        }

        // Get $_SERVER superglobal
        $server = [];
        if ($jieldRequest instanceof PhpEnvironmentRequest) {
            $server = $jieldRequest->getServer()->toArray();
        } elseif ($_SERVER !== []) {
            $server = $_SERVER;
        }

        $server['REQUEST_METHOD'] = $jieldRequest->getMethod();

        // Seed headers with HTTP auth information
        $headers = $headers->toArray();
        if (isset($server['PHP_AUTH_USER'])) {
            $headers['PHP_AUTH_USER'] = $server['PHP_AUTH_USER'];
        }

        if (isset($server['PHP_AUTH_PW'])) {
            $headers['PHP_AUTH_PW'] = $server['PHP_AUTH_PW'];
        }

        // Ensure the bodyParams are passed as an array
        $bodyParams = $this->bodyParams() ?: [];

        return new OAuth2Request(
            query: $jieldRequest->getQuery()->toArray(),
            request: $bodyParams,
            attributes: [], // attributes
            cookies: [], // cookies
            files: [], // files
            server: $server,
            content: $jieldRequest->getContent(),
            headers: $headers
        );
    }

    /**
     * Convert the OAuth2 response to a \Laminas\Http\Response
     */
    private function setHttpResponse(OAuth2Response $response): Response
    {
        $httpResponse = $this->getResponse();
        Assert::isInstanceOf(value: $httpResponse, class: Response::class, message: 'Cannot convert OAuth2Response to HTTP response');

        $httpResponse->setStatusCode(code: $response->getStatusCode());

        $headers = $httpResponse->getHeaders();
        $headers->addHeaders(headers: $response->getHttpHeaders());
        $headers->addHeaderLine(headerFieldNameOrLine: 'Content-type', fieldValue: 'application/json');

        $httpResponse->setContent(value: $response->getResponseBody());
        return $httpResponse;
    }

    /**
     * Retrieve the OAuth2\Server instance.
     *
     * If not already created by the composed $serverFactory, that callable
     * is invoked with the provided $type as an argument, and the value
     * returned.
     *
     * @param string|null $type
     * @return OAuth2Server
     * @throws \RuntimeException If the factory does not return an OAuth2Server instance.
     */
    private function getOAuth2Server(?string $type): OAuth2Server
    {
        if ($this->server instanceof OAuth2Server) {
            return $this->server;
        }

        $server = ($this->serverFactory)($type);
        if (!$server instanceof OAuth2Server) {
            throw new RuntimeException(message: sprintf(
                'OAuth2\Server factory did not return a valid instance; received %s',
                get_debug_type(value: $server)
            ));
        }

        $this->server = $server;
        return $server;
    }
}
