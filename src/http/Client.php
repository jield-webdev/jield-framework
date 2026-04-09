<?php

namespace Jield\Http;

use ArrayIterator;
use Jield\Http\Client\Adapter\Curl;
use Jield\Http\Client\Adapter\Socket;
use Jield\Http\Client\Exception\RuntimeException;
use Jield\Http\Header\SetCookie;
use Jield\Stdlib\ArrayUtils;
use Jield\Stdlib\DispatchableInterface;
use Jield\Stdlib\ErrorHandler;
use Jield\Stdlib\RequestInterface;
use Jield\Stdlib\ResponseInterface;
use Laminas\Uri\Http;
use Traversable;
use function array_merge;
use function base64_encode;
use function basename;
use function class_exists;
use function defined;
use function explode;
use function fclose;
use function file_get_contents;
use function finfo_file;
use function finfo_open;
use function fopen;
use function fstat;
use function func_get_arg;
use function func_num_args;
use function function_exists;
use function http_build_query;
use function in_array;
use function ini_get;
use function is_array;
use function is_int;
use function is_resource;
use function is_string;
use function md5;
use function microtime;
use function mime_content_type;
use function preg_match;
use function preg_quote;
use function rewind;
use function rtrim;
use function sprintf;
use function str_replace;
use function stream_get_meta_data;
use function stripos;
use function strlen;
use function strpos;
use function strrpos;
use function strtolower;
use function strtoupper;
use function substr;
use function sys_get_temp_dir;
use function tempnam;
use function trim;
use const CURLAUTH_DIGEST;
use const CURLOPT_HTTPAUTH;
use const CURLOPT_USERPWD;
use const FILEINFO_MIME;

/**
 * Http client
 */
class Client implements DispatchableInterface
{
    /**
     * @const string Supported HTTP Authentication methods
     */
    public const string AUTH_BASIC = 'basic';
    public const string AUTH_DIGEST = 'digest';

    /**
     * @const string POST data encoding methods
     */
    public const string ENC_URLENCODED = 'application/x-www-form-urlencoded';
    public const string ENC_FORMDATA = 'multipart/form-data';

    /**
     * @const string DIGEST Authentication
     */
    public const string DIGEST_REALM = 'realm';
    public const string DIGEST_QOP = 'qop';
    public const string DIGEST_NONCE = 'nonce';
    public const string DIGEST_OPAQUE = 'opaque';
    public const string DIGEST_NC = 'nc';
    public const string DIGEST_CNONCE = 'cnonce';

    /** @var Response */
    protected Response $response;

    /** @var Request */
    protected Request $request;

    /** @var Client\Adapter\AdapterInterface */
    protected Client\Adapter\AdapterInterface $adapter;

    /** @var array */
    protected array $auth = [];

    /** @var string */
    protected string $streamName;

    /** @var resource|null */
    protected $streamHandle;

    /** @var array of Header\SetCookie */
    protected array $cookies = [];

    /** @var string */
    protected string $encType = '';

    /** @var Request */
    protected Request $lastRawRequest;

    /** @var Response */
    protected Response $lastRawResponse;

    /** @var int */
    protected int $redirectCounter = 0;

    /**
     * Configuration array, set using the constructor or using ::setOptions()
     *
     * @var array
     */
    protected array $config
        = [
            'maxredirects'    => 5,
            'strictredirects' => false,
            'useragent'       => 'Jield_Http_Client',
            'timeout'         => 10,
            'connecttimeout'  => null,
            'adapter'         => Socket::class,
            'httpversion'     => AbstractMessage::VERSION_11,
            'storeresponse'   => true,
            'keepalive'       => false,
            'outputstream'    => false,
            'encodecookies'   => true,
            'argseparator'    => null,
            'rfc3986strict'   => false,
            'sslcafile'       => null,
            'sslcapath'       => null,
        ];

    /**
     * Fileinfo magic database resource
     *
     * This variable is populated the first time _detectFileMimeType is called
     * and is then reused on every call to this method
     *
     * @var resource
     */
    protected static $fileInfoDb;

    /**
     * Constructor
     *
     * @param string|null $uri
     * @param Traversable|array|null $options
     */
    public function __construct(?string $uri = null, Traversable|array|null $options = null)
    {
        if ($uri !== null) {
            $this->setUri(uri: $uri);
        }
        if ($options !== null) {
            $this->setOptions(options: $options);
        }
    }

    /**
     * Set configuration parameters for this HTTP client
     *
     * @param Traversable|array $options
     * @return $this
     * @throws Client\Exception\InvalidArgumentException
     */
    public function setOptions(Traversable|array $options = []): static
    {
        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray(iterator: $options);
        }
        if (!is_array(value: $options)) {
            throw new Client\Exception\InvalidArgumentException(message: 'Config parameter is not valid');
        }

        /** Config Key Normalization */
        foreach ($options as $k => $v) {
            $this->config[str_replace(search: ['-', '_', ' ', '.'], replace: '', subject: strtolower(string: $k))] = $v; // replace w/ normalized
        }

        // Pass configuration options to the adapter if it exists
        if ($this->adapter instanceof Client\Adapter\AdapterInterface) {
            $this->adapter->setOptions(options: $options);
        }

        return $this;
    }

    /**
     * Load the connection adapter
     *
     * While this method is not called more than one for a client, it is
     * separated from ->request() to preserve logic and readability
     *
     * @param Client\Adapter\AdapterInterface|string $adapter
     * @return $this
     * @throws Client\Exception\InvalidArgumentException
     */
    public function setAdapter(string|Client\Adapter\AdapterInterface $adapter): static
    {
        if (is_string(value: $adapter)) {
            if (!class_exists(class: $adapter)) {
                throw new Client\Exception\InvalidArgumentException(
                    message: 'Unable to locate adapter class "' . $adapter . '"'
                );
            }
            $adapter = new $adapter();
        }

        if (!$adapter instanceof Client\Adapter\AdapterInterface) {
            throw new Client\Exception\InvalidArgumentException(message: 'Passed adapter is not a HTTP connection adapter');
        }

        $this->adapter = $adapter;
        $config        = $this->config;
        unset($config['adapter']);
        $this->adapter->setOptions(options: $config);
        return $this;
    }

    /**
     * Load the connection adapter
     *
     * @return Client\Adapter\AdapterInterface
     */
    public function getAdapter(): Client\Adapter\AdapterInterface
    {
        if (!$this->adapter) {
            $this->setAdapter(adapter: $this->config['adapter']);
        }

        return $this->adapter;
    }

    /**
     * Set request
     *
     * @return $this
     */
    public function setRequest(Request $request): static
    {
        $this->request = $request;
        return $this;
    }

    /**
     * Get Request
     *
     * @return Request
     */
    public function getRequest(): Request
    {
        if (empty($this->request)) {
            $this->request = new Request();
            $this->request->setAllowCustomMethods(strictMethods: false);
        }
        return $this->request;
    }

    /**
     * Set response
     *
     * @return $this
     */
    public function setResponse(Response $response): static
    {
        $this->response = $response;
        return $this;
    }

    /**
     * Get Response
     *
     * @return Response
     */
    public function getResponse(): Response
    {
        if (empty($this->response)) {
            $this->response = new Response();
        }
        return $this->response;
    }

    /**
     * Get the last request (as a string)
     *
     * @return string
     */
    public function getLastRawRequest(): Request|string
    {
        return $this->lastRawRequest;
    }

    /**
     * Get the last response (as a string)
     *
     * @return string
     */
    public function getLastRawResponse(): string|Response
    {
        return $this->lastRawResponse;
    }

    /**
     * Get the redirections count
     *
     * @return int
     */
    public function getRedirectionsCount(): int
    {
        return $this->redirectCounter;
    }

    /**
     * Set Uri (to the request)
     *
     * @param string|Http $uri
     * @return $this
     */
    public function setUri(string|Http $uri): static
    {
        if (!empty($uri)) {
            // remember host of last request
            $lastHost = $this->getRequest()->getUri()->getHost();
            $this->getRequest()->setUri(uri: $uri);

            // if host changed, the HTTP authentication should be cleared for security
            // reasons, see #4215 for a discussion - currently authentication is also
            // cleared for peer subdomains due to technical limits
            $nextHost = $this->getRequest()->getUri()->getHost();
            if (!empty($lastHost) && !preg_match(pattern: '/' . preg_quote(str: $lastHost, delimiter: '/') . '$/i', subject: $nextHost)) {
                $this->clearAuth();
            }

            $uri      = $this->getUri();
            $user     = $uri->getUser();
            $password = $uri->getPassword();

            // Set auth if username and password has been specified in the uri
            if ($user && $password) {
                $this->setAuth(user: $user, password: $password);
            }

            // We have no ports, set the defaults
            if (!$uri->getPort() && $uri->isAbsolute()) {
                $uri->setPort(port: $uri->getScheme() === 'https' ? 443 : 80);
            }
        }
        return $this;
    }

    /**
     * Get uri (from the request)
     *
     * @return Http
     */
    public function getUri(): Http
    {
        return $this->getRequest()->getUri();
    }

    /**
     * Set the HTTP method (to the request)
     *
     * @param string $method
     * @return $this
     */
    public function setMethod(string $method): static
    {
        $method = $this->getRequest()->setMethod(method: $method)->getMethod();

        if (
            empty($this->encType)
            && in_array(
                needle: $method,
                haystack: [
                    Request::METHOD_POST,
                    Request::METHOD_PUT,
                    Request::METHOD_DELETE,
                    Request::METHOD_PATCH,
                    Request::METHOD_OPTIONS,
                ],
                strict: true
            )
        ) {
            $this->setEncType(encType: self::ENC_URLENCODED);
        }

        return $this;
    }

    /**
     * Get the HTTP method
     *
     * @return string
     */
    public function getMethod(): string
    {
        return $this->getRequest()->getMethod();
    }

    /**
     * Set the query string argument separator
     *
     * @param string $argSeparator
     * @return $this
     */
    public function setArgSeparator(string $argSeparator): static
    {
        $this->setOptions(options: ['argseparator' => $argSeparator]);
        return $this;
    }

    /**
     * Get the query string argument separator
     *
     * @return string
     */
    public function getArgSeparator(): string
    {
        $argSeparator = $this->config['argseparator'];
        if (empty($argSeparator)) {
            $argSeparator = ini_get(option: 'arg_separator.output');
            $this->setArgSeparator(argSeparator: $argSeparator);
        }
        return $argSeparator;
    }

    /**
     * Set the encoding type and the boundary (if any)
     *
     * @param string $encType
     * @param string|null $boundary
     * @return $this
     */
    public function setEncType(string $encType, ?string $boundary = null): static
    {
        if (null === $encType || empty($encType)) {
            $this->encType = null;
            return $this;
        }

        if (!empty($boundary)) {
            $encType .= sprintf('; boundary=%s', $boundary);
        }

        $this->encType = $encType;
        return $this;
    }

    /**
     * Get the encoding type
     *
     * @return string
     */
    public function getEncType(): string
    {
        return $this->encType;
    }

    /**
     * Set raw body (for advanced use cases)
     *
     * @param string $body
     * @return $this
     */
    public function setRawBody(string $body): static
    {
        $this->getRequest()->setContent(value: $body);
        return $this;
    }

    /**
     * Set the POST parameters
     *
     * @return $this
     */
    public function setParameterPost(array $post): static
    {
        $this->getRequest()->getPost()->fromArray(values: $post);
        return $this;
    }

    /**
     * Set the GET parameters
     *
     * @return $this
     */
    public function setParameterGet(array $query): static
    {
        $this->getRequest()->getQuery()->fromArray(values: $query);
        return $this;
    }

    /**
     * Reset all the HTTP parameters (request, response, etc)
     *
     * @param bool $clearCookies Also clear all valid cookies? (defaults to false)
     * @return $this
     */
    public function resetParameters(bool $clearCookies = false): static
    {
        $clearAuth = true;
        if (func_num_args() > 1) {
            $clearAuth = func_get_arg(position: 1);
        }

        $uri = $this->getUri();

        $this->streamName      = null;
        $this->encType         = null;
        $this->request         = null;
        $this->response        = null;
        $this->lastRawRequest  = null;
        $this->lastRawResponse = null;

        $this->setUri(uri: $uri);

        if ($clearCookies) {
            $this->clearCookies();
        }

        if ($clearAuth) {
            $this->clearAuth();
        }

        return $this;
    }

    /**
     * Return the current cookies
     *
     * @return array
     */
    public function getCookies(): array
    {
        return $this->cookies;
    }

    /**
     * Get the cookie Id (name+domain+path)
     *
     * @param SetCookie|Header\Cookie $cookie
     * @return string|bool
     */
    protected function getCookieId(SetCookie|Header\Cookie $cookie): bool|string
    {
        if ($cookie instanceof Header\SetCookie || $cookie instanceof Header\Cookie) {
            return $cookie->getName() . $cookie->getDomain() . $cookie->getPath();
        }
        return false;
    }

    /**
     * Add a cookie
     *
     * @param array|string|ArrayIterator|SetCookie $cookie
     * @param string|null $value
     * @param string|null $expire
     * @param string|null $path
     * @param string|null $domain
     * @param bool $secure
     * @param bool $httponly
     * @param string|null $maxAge
     * @param string|null $version
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function addCookie(
        SetCookie|array|string|ArrayIterator $cookie,
        ?string                              $value = null,
        ?string                              $expire = null,
        ?string                              $path = null,
        ?string                              $domain = null,
        bool                                 $secure = false,
        bool                                 $httponly = true,
        ?string                              $maxAge = null,
        ?string                              $version = null
    ): static
    {
        if (is_array(value: $cookie) || $cookie instanceof ArrayIterator) {
            foreach ($cookie as $setCookie) {
                if ($setCookie instanceof Header\SetCookie) {
                    $this->cookies[$this->getCookieId(cookie: $setCookie)] = $setCookie;
                } else {
                    throw new Exception\InvalidArgumentException(message: 'The cookie parameter is not a valid Set-Cookie type');
                }
            }
        } elseif (is_string(value: $cookie) && $value !== null) {
            $setCookie                                             = new SetCookie(
                name: $cookie,
                value: $value,
                expires: $expire,
                path: $path,
                domain: $domain,
                secure: $secure,
                httponly: $httponly,
                maxAge: $maxAge,
                version: $version
            );
            $this->cookies[$this->getCookieId(cookie: $setCookie)] = $setCookie;
        } elseif ($cookie instanceof Header\SetCookie) {
            $this->cookies[$this->getCookieId(cookie: $cookie)] = $cookie;
        } else {
            throw new Exception\InvalidArgumentException(message: 'Invalid parameter type passed as Cookie');
        }
        return $this;
    }

    /**
     * Set an array of cookies
     *
     * @param array|SetCookie[] $cookies Cookies as name=>value pairs or instances of SetCookie.
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function setCookies(array $cookies): static
    {
        if (is_array(value: $cookies)) {
            $this->clearCookies();
            foreach ($cookies as $name => $value) {
                if ($value instanceof SetCookie) {
                    $this->addCookie(cookie: $value);
                } else {
                    $this->addCookie(cookie: $name, value: $value);
                }
            }
        } else {
            throw new Exception\InvalidArgumentException(message: 'Invalid cookies passed as parameter, it must be an array');
        }
        return $this;
    }

    /**
     * Clear all the cookies
     */
    public function clearCookies(): void
    {
        $this->cookies = [];
    }

    /**
     * Set the headers (for the request)
     *
     * @param array|Headers $headers
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function setHeaders(array|Headers $headers): static
    {
        if (is_array(value: $headers)) {
            $newHeaders = new Headers();
            $newHeaders->addHeaders(headers: $headers);
            $this->getRequest()->setHeaders(headers: $newHeaders);
        } elseif ($headers instanceof Headers) {
            $this->getRequest()->setHeaders(headers: $headers);
        } else {
            throw new Exception\InvalidArgumentException(message: 'Invalid parameter headers passed');
        }
        return $this;
    }

    /**
     * Check if exists the header type specified
     *
     * @param string $name
     * @return bool
     */
    public function hasHeader(string $name): bool
    {
        $headers = $this->getRequest()->getHeaders();

        if ($headers instanceof Headers) {
            return $headers->has(name: $name);
        }

        return false;
    }

    /**
     * Get the header value of the request
     *
     * @param string $name
     * @return string|bool
     */
    public function getHeader(string $name): bool|string
    {
        $headers = $this->getRequest()->getHeaders();

        if (($headers instanceof Headers) && $headers->get(name: $name)) {
            return $headers->get(name: $name)->getFieldValue();
        }
        return false;
    }

    /**
     * Set streaming for received data
     *
     * @param bool|string $streamfile Stream file, true for temp file, false/null for no streaming
     * @return $this
     */
    public function setStream(bool|string $streamfile = true): static
    {
        $this->setOptions(options: ['outputstream' => $streamfile]);
        return $this;
    }

    /**
     * Get status of streaming for received data
     *
     * @return bool|string
     */
    public function getStream(): bool|string
    {
        if (null !== $this->streamName) {
            return $this->streamName;
        }

        return $this->config['outputstream'];
    }

    /**
     * Create temporary stream
     *
     * @return resource
     * @throws Exception\RuntimeException
     */
    protected function openTempStream()
    {
        $this->streamName = $this->config['outputstream'];

        if (!is_string(value: $this->streamName)) {
            // If name is not given, create temp name
            $this->streamName = tempnam(
                directory: $this->config['streamtmpdir'] ?? sys_get_temp_dir(),
                prefix: self::class
            );
        }

        ErrorHandler::start();
        $fp    = fopen(filename: $this->streamName, mode: 'w+b');
        $error = ErrorHandler::stop();
        if (false === $fp) {
            if ($this->adapter instanceof Client\Adapter\AdapterInterface) {
                $this->adapter->close();
            }
            throw new Exception\RuntimeException(message: sprintf('Could not open temp file %s', $this->streamName), code: 0, previous: $error);
        }

        return $fp;
    }

    /**
     * Create a HTTP authentication "Authorization:" header according to the
     * specified user, password and authentication method.
     *
     * @param string $user
     * @param string $password
     * @param string $type
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function setAuth(string $user, string $password, string $type = self::AUTH_BASIC): static
    {
        if (!defined(constant_name: 'static::AUTH_' . strtoupper(string: $type))) {
            throw new Exception\InvalidArgumentException(message: sprintf(
                'Invalid or not supported authentication type: \'%s\'',
                $type
            ));
        }

        if (empty($user)) {
            throw new Exception\InvalidArgumentException(message: 'The username cannot be empty');
        }

        $this->auth = [
            'user'     => $user,
            'password' => $password,
            'type'     => $type,
        ];

        return $this;
    }

    /**
     * Clear http authentication
     */
    public function clearAuth(): void
    {
        $this->auth = [];
    }

    /**
     * Calculate the response value according to the HTTP authentication type
     *
     * @see http://www.faqs.org/rfcs/rfc2617.html
     *
     * @param string $user
     * @param string $password
     * @param string $type
     * @param array $digest
     * @param string|null $entityBody
     * @throws Exception\InvalidArgumentException
     * @return string|bool
     */
    protected function calcAuthDigest(string $user, string $password, string $type = self::AUTH_BASIC, array $digest = [], ?string $entityBody = null): bool|string
    {
        if (!defined(constant_name: 'self::AUTH_' . strtoupper(string: $type))) {
            throw new Exception\InvalidArgumentException(message: sprintf(
                'Invalid or not supported authentication type: \'%s\'',
                $type
            ));
        }
        $response = false;
        switch (strtolower(string: $type)) {
            case self::AUTH_BASIC:
                // In basic authentication, the user name cannot contain ":"
                if (strpos(haystack: $user, needle: ':') !== false) {
                    throw new Exception\InvalidArgumentException(
                        message: 'The user name cannot contain \':\' in Basic HTTP authentication'
                    );
                }
                $response = base64_encode(string: $user . ':' . $password);
                break;
            case self::AUTH_DIGEST:
                if (empty($digest)) {
                    throw new Exception\InvalidArgumentException(message: 'The digest cannot be empty');
                }
                foreach ($digest as $key => $value) {
                    if (!defined(constant_name: 'self::DIGEST_' . strtoupper(string: $key))) {
                        throw new Exception\InvalidArgumentException(message: sprintf(
                            'Invalid or not supported digest authentication parameter: \'%s\'',
                            $key
                        ));
                    }
                }
                $ha1 = md5(string: $user . ':' . $digest['realm'] . ':' . $password);
                if (empty($digest['qop']) || strtolower(string: $digest['qop']) === 'auth') {
                    $ha2 = md5(string: $this->getMethod() . ':' . $this->getUri()->getPath());
                } elseif (strtolower(string: $digest['qop']) === 'auth-int') {
                    if (empty($entityBody)) {
                        throw new Exception\InvalidArgumentException(
                            message: 'I cannot use the auth-int digest authentication without the entity body'
                        );
                    }
                    $ha2 = md5(string: $this->getMethod() . ':' . $this->getUri()->getPath() . ':' . md5(string: $entityBody));
                }
                if (empty($digest['qop'])) {
                    $response = md5(string: $ha1 . ':' . $digest['nonce'] . ':' . $ha2);
                } else {
                    $response = md5(string: $ha1 . ':' . $digest['nonce'] . ':' . $digest['nc']
                        . ':' . $digest['cnonce'] . ':' . $digest['qoc'] . ':' . $ha2);
                }
                break;
        }
        return $response;
    }

    /**
     * Dispatch
     *
     * @return ResponseInterface
     */
    public function dispatch(RequestInterface $request, ?ResponseInterface $response = null): ResponseInterface|Response
    {
        return $this->send(request: $request);
    }

    /**
     * Send HTTP request
     *
     * @return Response
     * @throws Exception\RuntimeException
     * @throws \RuntimeException
     */
    public function send(?Request $request = null): Response
    {
        if ($request !== null) {
            $this->setRequest(request: $request);
        }

        $this->redirectCounter = 0;

        $adapter = $this->getAdapter();

        // Send the first request. If redirected, continue.
        do {
            // uri
            $uri = $this->getUri();

            // query
            $query = $this->getRequest()->getQuery();

            if (!empty($query)) {
                $queryArray = $query->toArray();

                if (!empty($queryArray)) {
                    $newUri      = $uri->toString();
                    $queryString = http_build_query(data: $queryArray, numeric_prefix: '', arg_separator: $this->getArgSeparator());

                    if ($this->config['rfc3986strict']) {
                        $queryString = str_replace(search: '+', replace: '%20', subject: $queryString);
                    }

                    if (strpos(haystack: $newUri, needle: '?') !== false) {
                        $newUri .= $this->getArgSeparator() . $queryString;
                    } else {
                        $newUri .= '?' . $queryString;
                    }

                    $uri = new Http(uri: $newUri);
                }
            }
            // If we have no ports, set the defaults
            if (!$uri->getPort() && $uri->isAbsolute()) {
                $uri->setPort(port: $uri->getScheme() === 'https' ? 443 : 80);
            }

            // method
            $method = $this->getRequest()->getMethod();

            // this is so the correct Encoding Type is set
            $this->setMethod(method: $method);

            // body
            $body = $this->prepareBody();

            // headers
            $headers = $this->prepareHeaders(body: $body, uri: $uri);

            $secure = $uri->getScheme() === 'https';

            // cookies
            $cookie = $this->prepareCookies(domain: $uri->getHost(), path: $uri->getPath(), secure: $secure);
            if ($cookie->getFieldValue()) {
                $headers['Cookie'] = $cookie->getFieldValue();
            }

            // check that adapter supports streaming before using it
            if (is_resource(value: $body) && !$adapter instanceof Client\Adapter\StreamInterface) {
                throw new RuntimeException(message: 'Adapter does not support streaming');
            }

            $this->streamHandle = null;
            // calling protected method to allow extending classes
            // to wrap the interaction with the adapter
            $response           = $this->doRequest(uri: $uri, method: $method, secure: $secure, headers: $headers, body: $body);
            $stream             = $this->streamHandle;
            $this->streamHandle = null;

            if (!$response) {
                if ($stream !== null) {
                    fclose(stream: $stream);
                }
                throw new Exception\RuntimeException(message: 'Unable to read response, or response is empty');
            }

            if ($this->config['storeresponse']) {
                $this->lastRawResponse = $response;
            } else {
                $this->lastRawResponse = null;
            }

            if ($this->config['outputstream']) {
                if ($stream === null) {
                    $stream = $this->getStream();
                    if (!is_resource(value: $stream) && is_string(value: $stream)) {
                        $stream = fopen(filename: $stream, mode: 'r');
                    }
                }
                $streamMetaData = stream_get_meta_data(stream: $stream);
                if ($streamMetaData['seekable']) {
                    rewind(stream: $stream);
                }
                // cleanup the adapter
                $adapter->setOutputStream(stream: null);
                $response = Response\Stream::fromStream(responseString: $response, stream: $stream);
                $response->setStreamName(streamName: $this->streamName);
                if (!is_string(value: $this->config['outputstream'])) {
                    // we used temp name, will need to clean up
                    $response->setCleanup(cleanup: true);
                }
            } else {
                $response = $this->getResponse()->fromString(string: $response);
            }

            // Get the cookies from response (if any)
            $setCookies = $response->getCookie();
            if (!empty($setCookies)) {
                $this->addCookie(cookie: $setCookies);
            }

            // If we got redirected, look for the Location header
            if ($response->isRedirect() && ($response->getHeaders()->has(name: 'Location'))) {
                // Avoid problems with buggy servers that add whitespace at the
                // end of some headers
                $location = trim(string: $response->getHeaders()->get(name: 'Location')->getFieldValue());

                // Check whether we send the exact same request again, or drop the parameters
                // and send a GET request
                if (
                    $response->getStatusCode() === 303
                    || ((!$this->config['strictredirects'])
                        && ($response->getStatusCode() === 302 || $response->getStatusCode() === 301))
                ) {
                    $this->resetParameters(false, false);
                    $this->setMethod(method: Request::METHOD_GET);
                }

                // If we got a well formed absolute URI
                if (
                    ($scheme = substr(string: $location, offset: 0, length: 6))
                    && ($scheme === 'http:/' || $scheme === 'https:')
                ) {
                    // setURI() clears parameters if host changed, see #4215
                    $this->setUri(uri: $location);
                } else {
                    // Split into path and query and set the query
                    if (strpos(haystack: $location, needle: '?') !== false) {
                        [$location, $query] = explode(separator: '?', string: $location, limit: 2);
                    } else {
                        $query = '';
                    }
                    $this->getUri()->setQuery(query: $query);

                    // Else, if we got just an absolute path, set it
                    if (strpos(haystack: $location, needle: '/') === 0) {
                        $this->getUri()->setPath(path: $location);
                        // Else, assume we have a relative path
                    } else {
                        // Get the current path directory, removing any trailing slashes
                        $path = $this->getUri()->getPath();
                        $path = rtrim(string: substr(string: $path, offset: 0, length: strrpos(haystack: $path, needle: '/')), characters: '/');
                        $this->getUri()->setPath(path: $path . '/' . $location);
                    }
                }
                ++$this->redirectCounter;
            } else {
                // If we didn't get any location, stop redirecting
                break;
            }
        } while ($this->redirectCounter <= $this->config['maxredirects']);

        $this->response = $response;
        return $response;
    }

    /**
     * Fully reset the HTTP client (auth, cookies, request, response, etc.)
     *
     * @return $this
     */
    public function reset(): static
    {
        $this->resetParameters();
        $this->clearAuth();
        $this->clearCookies();

        return $this;
    }

    /**
     * Set a file to upload (using a POST request)
     *
     * Can be used in two ways:
     *
     * 1. $data is null (default): $filename is treated as the name if a local file which
     * will be read and sent. Will try to guess the content type using mime_content_type().
     * 2. $data is set - $filename is sent as the file name, but $data is sent as the file
     * contents and no file is read from the file system. In this case, you need to
     * manually set the Content-Type ($ctype) or it will default to
     * application/octet-stream.
     *
     * @param string $filename Name of file to upload, or name to save as
     * @param string $formname Name of form element to send as
     * @param string|null $data Data to send (if null, $filename is read and sent)
     * @param string|null $ctype Content type to use (if $data is set and $ctype is
     *                null, will be application/octet-stream)
     * @return $this
     * @throws Exception\RuntimeException
     */
    public function setFileUpload(string $filename, string $formname, ?string $data = null, ?string $ctype = null): static
    {
        if ($data === null) {
            ErrorHandler::start();
            $data  = file_get_contents(filename: $filename);
            $error = ErrorHandler::stop();
            if ($data === false) {
                throw new Exception\RuntimeException(message: sprintf(
                    'Unable to read file \'%s\' for upload',
                    $filename
                ), code: 0, previous: $error);
            }
            if (!$ctype) {
                $ctype = $this->detectFileMimeType(file: $filename);
            }
        }

        $this->getRequest()->getFiles()->set(name: $filename, value: [
            'formname' => $formname,
            'filename' => basename(path: $filename),
            'ctype'    => $ctype,
            'data'     => $data,
        ]);

        return $this;
    }

    /**
     * Remove a file to upload
     *
     * @param string $filename
     * @return bool
     */
    public function removeFileUpload(string $filename): bool
    {
        $file = $this->getRequest()->getFiles()->get(name: $filename);
        if (!empty($file)) {
            $this->getRequest()->getFiles()->set(name: $filename, value: null);
            return true;
        }
        return false;
    }

    /**
     * Prepare Cookies
     *
     * @param string $domain
     * @param string $path
     * @param bool $secure
     * @return  Header\Cookie|bool
     */
    protected function prepareCookies(string $domain, string $path, bool $secure): Header\Cookie|bool
    {
        $validCookies = [];

        if (!empty($this->cookies)) {
            foreach ($this->cookies as $id => $cookie) {
                if ($cookie->isExpired()) {
                    unset($this->cookies[$id]);
                    continue;
                }

                if ($cookie->isValidForRequest($domain, $path, $secure)) {
                    // OAM hack some domains try to set the cookie multiple times
                    $validCookies[$cookie->getName()] = $cookie;
                }
            }
        }

        $cookies = Header\Cookie::fromSetCookieArray(setCookies: $validCookies);
        $cookies->setEncodeValue(encodeValue: $this->config['encodecookies']);

        return $cookies;
    }

    /**
     * Prepare the request headers
     *
     * @param string $body
     * @param Http $uri
     * @return array
     * @throws Exception\RuntimeException
     */
    protected function prepareHeaders(string $body, Http $uri): array
    {
        $headers = [];

        // Set the host header
        if ($this->config['httpversion'] === AbstractMessage::VERSION_11) {
            $host = $uri->getHost();
            // If the port is not default, add it
            if (
                !(($uri->getScheme() === 'http' && $uri->getPort() === 80)
                    || ($uri->getScheme() === 'https' && $uri->getPort() === 443))
            ) {
                $host .= ':' . $uri->getPort();
            }

            $headers['Host'] = $host;
        }

        // Set the connection header
        if (!$this->getRequest()->getHeaders()->has(name: 'Connection') && !$this->config['keepalive']) {
            $headers['Connection'] = 'close';
        }

        // Set the Accept-encoding header if not set - depending on whether
        // zlib is available or not.
        if (!$this->getRequest()->getHeaders()->has(name: 'Accept-Encoding')) {
            if (empty($this->config['outputstream']) && function_exists(function: 'gzinflate')) {
                $headers['Accept-Encoding'] = 'gzip, deflate';
            } else {
                $headers['Accept-Encoding'] = 'identity';
            }
        }

        // Set the user agent header
        if (!$this->getRequest()->getHeaders()->has(name: 'User-Agent') && isset($this->config['useragent'])) {
            $headers['User-Agent'] = $this->config['useragent'];
        }

        // Set HTTP authentication if needed
        if (!empty($this->auth)) {
            switch ($this->auth['type']) {
                case self::AUTH_BASIC:
                    $auth = $this->calcAuthDigest(user: $this->auth['user'], password: $this->auth['password'], type: $this->auth['type']);
                    if ($auth !== false) {
                        $headers['Authorization'] = 'Basic ' . $auth;
                    }
                    break;
                case self::AUTH_DIGEST:
                    if (!$this->adapter instanceof Client\Adapter\Curl) {
                        throw new Exception\RuntimeException(message: sprintf(
                            'The digest authentication is only available for curl adapters (%s)',
                            Curl::class
                        ));
                    }

                    $this->adapter->setCurlOption(option: CURLOPT_HTTPAUTH, value: CURLAUTH_DIGEST);
                    $this->adapter->setCurlOption(option: CURLOPT_USERPWD, value: $this->auth['user'] . ':' . $this->auth['password']);
            }
        }

        // Content-type
        $encType = $this->getEncType();
        if (!empty($encType)) {
            $headers['Content-Type'] = $encType;
        }

        if (!empty($body)) {
            if (is_resource(value: $body)) {
                $fstat                     = fstat(stream: $body);
                $headers['Content-Length'] = $fstat['size'];
            } else {
                $headers['Content-Length'] = strlen(string: $body);
            }
        }

        // Merge the headers of the request (if any)
        // here we need right 'http field' and not lowercase letters
        $requestHeaders = $this->getRequest()->getHeaders();
        foreach ($requestHeaders as $requestHeaderElement) {
            $headers[$requestHeaderElement->getFieldName()] = $requestHeaderElement->getFieldValue();
        }
        return $headers;
    }

    /**
     * Prepare the request body (for PATCH, POST and PUT requests)
     *
     * @return string
     * @throws \RuntimeException
     */
    protected function prepareBody(): string
    {
        // According to RFC2616, a TRACE request should not have a body.
        if ($this->getRequest()->isTrace()) {
            return '';
        }

        $rawBody = $this->getRequest()->getContent();
        if (!empty($rawBody)) {
            return $rawBody;
        }

        $body     = '';
        $hasFiles = false;

        if (!$this->getRequest()->getHeaders()->has(name: 'Content-Type')) {
            $hasFiles = !empty($this->getRequest()->getFiles()->toArray());
            // If we have files to upload, force encType to multipart/form-data
            if ($hasFiles) {
                $this->setEncType(encType: self::ENC_FORMDATA);
            }
        } else {
            $this->setEncType(encType: $this->getHeader(name: 'Content-Type'));
        }

        // If we have POST parameters or files, encode and add them to the body
        if (!empty($this->getRequest()->getPost()->toArray()) || $hasFiles) {
            if (stripos(haystack: $this->getEncType(), needle: self::ENC_FORMDATA) === 0) {
                $boundary = '---ZENDHTTPCLIENT-' . md5(string: microtime());
                $this->setEncType(encType: self::ENC_FORMDATA, boundary: $boundary);

                // Get POST parameters and encode them
                $params = self::flattenParametersArray(parray: $this->getRequest()->getPost()->toArray());
                foreach ($params as $pp) {
                    $body .= $this->encodeFormData(boundary: $boundary, name: $pp[0], value: $pp[1]);
                }

                // Encode files
                foreach ($this->getRequest()->getFiles()->toArray() as $file) {
                    $fhead = ['Content-Type' => $file['ctype']];
                    $body  .= $this->encodeFormData(
                        boundary: $boundary,
                        name: $file['formname'],
                        value: $file['data'],
                        filename: $file['filename'],
                        headers: $fhead
                    );
                }
                $body .= '--' . $boundary . '--' . "\r\n";
            } elseif (stripos(haystack: $this->getEncType(), needle: self::ENC_URLENCODED) === 0) {
                // Encode body as application/x-www-form-urlencoded
                $body = http_build_query(data: $this->getRequest()->getPost()->toArray(), numeric_prefix: '', arg_separator: '&');
            } else {
                throw new RuntimeException(message: sprintf(
                    'Cannot handle content type \'%s\' automatically',
                    $this->encType
                ));
            }
        }

        return $body;
    }

    /**
     * Attempt to detect the MIME type of a file using available extensions
     *
     * This method will try to detect the MIME type of a file. If the fileinfo
     * extension is available, it will be used. If not, the mime_magic
     * extension which is deprecated but is still available in many PHP setups
     * will be tried.
     *
     * If neither extension is available, the default application/octet-stream
     * MIME type will be returned
     *
     * @param string $file File path
     * @return string MIME type
     */
    protected function detectFileMimeType(string $file): ?string
    {
        $type = null;

        // First try with fileinfo functions
        if (function_exists(function: 'finfo_open')) {
            if (static::$fileInfoDb === null) {
                ErrorHandler::start();
                static::$fileInfoDb = finfo_open(flags: FILEINFO_MIME);
                ErrorHandler::stop();
            }

            if (static::$fileInfoDb) {
                $type = finfo_file(finfo: static::$fileInfoDb, filename: $file);
            }
        } elseif (function_exists(function: 'mime_content_type')) {
            $type = mime_content_type(filename: $file);
        }

        // Fallback to the default application/octet-stream
        if (!$type) {
            $type = 'application/octet-stream';
        }

        return $type;
    }

    /**
     * Encode data to a multipart/form-data part suitable for a POST request.
     *
     * @param string $boundary
     * @param string $name
     * @param mixed $value
     * @param string|null $filename
     * @param array $headers Associative array of optional headers @example ("Content-Transfer-Encoding" => "binary")
     * @return string
     */
    public function encodeFormData(string $boundary, string $name, mixed $value, ?string $filename = null, array $headers = []): string
    {
        $ret = '--' . $boundary . "\r\n"
            . 'Content-Disposition: form-data; name="' . $name . '"';

        if ($filename) {
            $ret .= '; filename="' . $filename . '"';
        }
        $ret .= "\r\n";

        foreach ($headers as $hname => $hvalue) {
            $ret .= $hname . ': ' . $hvalue . "\r\n";
        }
        $ret .= "\r\n";
        $ret .= $value . "\r\n";

        return $ret;
    }

    /**
     * Convert an array of parameters into a flat array of (key, value) pairs
     *
     * Will flatten a potentially multi-dimentional array of parameters (such
     * as POST parameters) into a flat array of (key, value) paris. In case
     * of multi-dimentional arrays, square brackets ([]) will be added to the
     * key to indicate an array.
     *
     * @param array $parray
     * @param string|null $prefix
     * @return array
     * @since 1.9
     */
    protected function flattenParametersArray(array $parray, ?string $prefix = null): array
    {
        if (!is_array(value: $parray)) {
            return $parray;
        }

        $parameters = [];

        foreach ($parray as $name => $value) {
            // Calculate array key
            if ($prefix) {
                if (is_int(value: $name)) {
                    $key = $prefix . '[]';
                } else {
                    $key = $prefix . sprintf('[%s]', $name);
                }
            } else {
                $key = $name;
            }

            if (is_array(value: $value)) {
                $parameters = array_merge($parameters, $this->flattenParametersArray(parray: $value, prefix: $key));
            } else {
                $parameters[] = [$key, $value];
            }
        }

        return $parameters;
    }

    /**
     * Separating this from send method allows subclasses to wrap
     * the interaction with the adapter
     *
     * @param string $method
     * @param bool $secure
     * @param array $headers
     * @param string $body
     * @return string the raw response
     * @throws Exception\RuntimeException
     */
    protected function doRequest(Http $uri, string $method, bool $secure = false, array $headers = [], string $body = ''): string
    {
        // Open the connection, send the request and read the response
        $this->adapter->connect(host: $uri->getHost(), port: $uri->getPort(), secure: $secure);

        if ($this->config['outputstream']) {
            if ($this->adapter instanceof Client\Adapter\StreamInterface) {
                $this->streamHandle = $this->openTempStream();
                $this->adapter->setOutputStream(stream: $this->streamHandle);
            } else {
                throw new Exception\RuntimeException(message: 'Adapter does not support streaming');
            }
        }
        // HTTP connection
        $this->lastRawRequest = $this->adapter->write(
            $method,
            $uri,
            $this->config['httpversion'],
            headers: $headers,
            body: $body
        );

        return $this->adapter->read();
    }

    /**
     * Create a HTTP authentication "Authorization:" header according to the
     * specified user, password and authentication method.
     *
     * @see http://www.faqs.org/rfcs/rfc2617.html
     *
     * @param string $user
     * @param string $password
     * @param string $type
     * @return string
     * @throws Client\Exception\InvalidArgumentException
     */
    public static function encodeAuthHeader(string $user, string $password, string $type = self::AUTH_BASIC): string
    {
        switch ($type) {
            case self::AUTH_BASIC:
                // In basic authentication, the user name cannot contain ":"
                if (strpos(haystack: $user, needle: ':') !== false) {
                    throw new Client\Exception\InvalidArgumentException(
                        message: 'The user name cannot contain \':\' in \'Basic\' HTTP authentication'
                    );
                }

                return 'Basic ' . base64_encode(string: $user . ':' . $password);

            //case self::AUTH_DIGEST:
            /**
             * @todo Implement digest authentication
             */
            //    break;

            default:
                throw new Client\Exception\InvalidArgumentException(message: sprintf(
                    'Not a supported HTTP authentication type: \'%s\'',
                    $type
                ));
        }
    }
}
