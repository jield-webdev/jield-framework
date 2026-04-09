<?php

namespace Jield\Http;

use ArrayIterator;
use Jield\Http\Header\HeaderInterface;
use Jield\Stdlib\Parameters;
use Jield\Stdlib\ParametersInterface;
use Jield\Stdlib\RequestInterface;
use Laminas\Uri\Exception as UriException;
use Laminas\Uri\Http as HttpUri;
use function array_key_exists;
use function array_shift;
use function defined;
use function explode;
use function implode;
use function is_string;
use function parse_str;
use function parse_url;
use function preg_match;
use function sprintf;
use function stristr;
use function strtoupper;

/**
 * HTTP Request
 *
 * @link      http://www.w3.org/Protocols/rfc2616/rfc2616-sec5.html#sec5
 */
class Request extends AbstractMessage implements RequestInterface
{
    /**#@+
     *
     * @const string METHOD constant names
     */
    public const string METHOD_OPTIONS = 'OPTIONS';
    public const string METHOD_GET = 'GET';
    public const string METHOD_HEAD = 'HEAD';
    public const string METHOD_POST = 'POST';
    public const string METHOD_PUT = 'PUT';
    public const string METHOD_DELETE = 'DELETE';
    public const string METHOD_TRACE = 'TRACE';
    public const string METHOD_CONNECT = 'CONNECT';
    public const string METHOD_PATCH = 'PATCH';
    public const string METHOD_PROPFIND = 'PROPFIND';
    /**#@-*/

    /** @var string */
    protected string $method = self::METHOD_GET;

    /** @var bool */
    protected bool $allowCustomMethods = true;

    /** @var string|HttpUri */
    protected HttpUri|string $uri;

    /** @var ParametersInterface */
    protected ParametersInterface $queryParams;

    /** @var ParametersInterface */
    protected ParametersInterface $postParams;

    /** @var ParametersInterface */
    protected ParametersInterface $fileParams;

    /**
     * A factory that produces a Request object from a well-formed Http Request string
     *
     * @param string $string
     * @param bool $allowCustomMethods
     * @return static
     * @throws Exception\InvalidArgumentException
     */
    public static function fromString(string $string, bool $allowCustomMethods = true): static
    {
        $request = new self();
        $request->setAllowCustomMethods(strictMethods: $allowCustomMethods);

        $lines = explode(separator: "\r\n", string: $string);

        // first line must be Method/Uri/Version string
        $matches = null;
        $methods = $allowCustomMethods
            ? '[\w-]+'
            : implode(
                separator: '|',
                array: [
                    self::METHOD_OPTIONS,
                    self::METHOD_GET,
                    self::METHOD_HEAD,
                    self::METHOD_POST,
                    self::METHOD_PUT,
                    self::METHOD_DELETE,
                    self::METHOD_TRACE,
                    self::METHOD_CONNECT,
                    self::METHOD_PATCH,
                ]
            );

        $regex     = '#^(?P<method>' . $methods . ')\s(?P<uri>[^ ]*)(?:\sHTTP\/(?P<version>\d+\.\d+)){0,1}#';
        $firstLine = array_shift(array: $lines);
        if (!preg_match(pattern: $regex, subject: $firstLine, matches: $matches)) {
            throw new Exception\InvalidArgumentException(
                message: 'A valid request line was not found in the provided string'
            );
        }

        $request->setMethod(method: $matches['method']);
        $request->setUri(uri: $matches['uri']);

        $parsedUri = parse_url(url: $matches['uri']);
        if (array_key_exists(key: 'query', array: $parsedUri)) {
            $parsedQuery = [];
            parse_str(string: $parsedUri['query'], result: $parsedQuery);
            $request->setQuery(query: new Parameters(values: $parsedQuery));
        }

        if (isset($matches['version'])) {
            $request->setVersion(version: $matches['version']);
        }

        if (empty($lines)) {
            return $request;
        }

        $isHeader = true;
        $headers  = $rawBody = [];
        while ($lines) {
            $nextLine = array_shift(array: $lines);
            if ($nextLine === '') {
                $isHeader = false;
                continue;
            }

            if ($isHeader) {
                if (preg_match(pattern: "/[\r\n]/", subject: $nextLine)) {
                    throw new Exception\RuntimeException(message: 'CRLF injection detected');
                }
                $headers[] = $nextLine;
                continue;
            }

            if (
                empty($rawBody)
                && preg_match(pattern: '/^[a-z0-9!#$%&\'*+.^_`|~-]+:$/i', subject: $nextLine)
            ) {
                throw new Exception\RuntimeException(message: 'CRLF injection detected');
            }

            $rawBody[] = $nextLine;
        }

        if ($headers) {
            $request->headers = implode(separator: "\r\n", array: $headers);
        }

        if ($rawBody) {
            $request->setContent(value: implode(separator: "\r\n", array: $rawBody));
        }

        return $request;
    }

    /**
     * Set the method for this request
     *
     * @param string $method
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function setMethod(string $method): static
    {
        $method = strtoupper(string: $method);
        if (!defined(constant_name: 'static::METHOD_' . $method) && !$this->getAllowCustomMethods()) {
            throw new Exception\InvalidArgumentException(message: 'Invalid HTTP method passed');
        }
        $this->method = $method;
        return $this;
    }

    /**
     * Return the method for this request
     *
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Set the URI/URL for this request, this can be a string or an instance of Laminas\Uri\Http
     *
     * @param string|HttpUri $uri
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function setUri(string|HttpUri $uri): static
    {
        if (is_string(value: $uri)) {
            try {
                $uri = new HttpUri(uri: $uri);
            } catch (UriException\InvalidUriPartException $e) {
                throw new Exception\InvalidArgumentException(
                    message: sprintf('Invalid URI passed as string (%s)', $uri),
                    code: $e->getCode(),
                    previous: $e
                );
            }
        } elseif (!$uri instanceof HttpUri) {
            throw new Exception\InvalidArgumentException(
                message: 'URI must be an instance of Laminas\Uri\Http or a string'
            );
        }
        $this->uri = $uri;

        return $this;
    }

    /**
     * Return the URI for this request object
     *
     * @return HttpUri
     */
    public function getUri(): string|HttpUri
    {
        if ($this->uri === null || is_string(value: $this->uri)) {
            $this->uri = new HttpUri(uri: $this->uri);
        }
        return $this->uri;
    }

    /**
     * Return the URI for this request object as a string
     *
     * @return string
     */
    public function getUriString(): string|HttpUri
    {
        if ($this->uri instanceof HttpUri) {
            return $this->uri->toString();
        }
        return $this->uri;
    }

    /**
     * Provide an alternate Parameter Container implementation for query parameters in this object,
     * (this is NOT the primary API for value setting, for that see getQuery())
     *
     * @return $this
     */
    public function setQuery(ParametersInterface $query): static
    {
        $this->queryParams = $query;
        return $this;
    }

    /**
     * Return the parameter container responsible for query parameters or a single query parameter
     *
     * @param string|null $name Parameter name to retrieve, or null to get the whole container.
     * @param mixed|null $default Default value to use when the parameter is missing.
     * @return ParametersInterface|mixed
     */
    public function getQuery(?string $name = null, mixed $default = null): mixed
    {
        if ($this->queryParams === null) {
            $this->queryParams = new Parameters();
        }

        if ($name === null) {
            return $this->queryParams;
        }

        return $this->queryParams->get(name: $name, default: $default);
    }

    /**
     * Provide an alternate Parameter Container implementation for post parameters in this object,
     * (this is NOT the primary API for value setting, for that see getPost())
     *
     * @return $this
     */
    public function setPost(ParametersInterface $post): static
    {
        $this->postParams = $post;
        return $this;
    }

    /**
     * Return the parameter container responsible for post parameters or a single post parameter.
     *
     * @param string|null $name Parameter name to retrieve, or null to get the whole container.
     * @param mixed|null $default Default value to use when the parameter is missing.
     * @return ParametersInterface|mixed
     */
    public function getPost(?string $name = null, mixed $default = null): mixed
    {
        if ($this->postParams === null) {
            $this->postParams = new Parameters();
        }

        if ($name === null) {
            return $this->postParams;
        }

        return $this->postParams->get(name: $name, default: $default);
    }

    /**
     * Return the Cookie header, this is the same as calling $request->getHeaders()->get('Cookie');
     *
     * @convenience $request->getHeaders()->get('Cookie');
     * @return Header\Cookie|bool
     */
    public function getCookie(): Header\Cookie|bool
    {
        return $this->getHeaders()->get(name: 'Cookie');
    }

    /**
     * Provide an alternate Parameter Container implementation for file parameters in this object,
     * (this is NOT the primary API for value setting, for that see getFiles())
     *
     * @return $this
     */
    public function setFiles(ParametersInterface $files): static
    {
        $this->fileParams = $files;
        return $this;
    }

    /**
     * Return the parameter container responsible for file parameters or a single file.
     *
     * @param string|null $name Parameter name to retrieve, or null to get the whole container.
     * @param mixed|null $default Default value to use when the parameter is missing.
     * @return ParametersInterface|mixed
     */
    public function getFiles(?string $name = null, mixed $default = null): mixed
    {
        if ($this->fileParams === null) {
            $this->fileParams = new Parameters();
        }

        if ($name === null) {
            return $this->fileParams;
        }

        return $this->fileParams->get(name: $name, default: $default);
    }

    /**
     * Return the header container responsible for headers or all headers of a certain name/type
     *
     * @param string|null $name Header name to retrieve, or null to get the whole container.
     * @param false|mixed $default Default value to use when the requested header is missing.
     * @return Headers|null
     * @see \Jield\Http\Headers::get()
     *
     */
    public function getHeaders(?string $name = null, false|mixed $default = false): ?Headers
    {
        if ($this->headers === null || is_string(value: $this->headers)) {
            // this is only here for fromString lazy loading
            $this->headers = is_string(value: $this->headers) ? Headers::fromString(string: $this->headers) : new Headers();
        }

        if ($name === null) {
            return $this->headers;
        }

        if ($this->headers->has(name: $name)) {
            return $this->headers->get(name: $name);
        }

        return $default;
    }

    /**
     * Get all headers of a certain name/type.
     *
     * @param string|null $name Header name to retrieve, or null to get the whole container.
     * @param false|mixed $default Default value to use when the requested header is missing.
     * @return Headers|bool|HeaderInterface|ArrayIterator
     * @see Request::getHeaders()
     *
     */
    public function getHeader(?string $name, false|mixed $default = false): HeaderInterface|bool|Headers|ArrayIterator
    {
        return $this->getHeaders(name: $name, default: $default);
    }

    /**
     * Is this an OPTIONS method request?
     *
     * @return bool
     */
    public function isOptions(): bool
    {
        return $this->method === self::METHOD_OPTIONS;
    }

    /**
     * Is this a PROPFIND method request?
     *
     * @return bool
     */
    public function isPropFind(): bool
    {
        return $this->method === self::METHOD_PROPFIND;
    }

    /**
     * Is this a GET method request?
     *
     * @return bool
     */
    public function isGet(): bool
    {
        return $this->method === self::METHOD_GET;
    }

    /**
     * Is this a HEAD method request?
     *
     * @return bool
     */
    public function isHead(): bool
    {
        return $this->method === self::METHOD_HEAD;
    }

    /**
     * Is this a POST method request?
     *
     * @return bool
     */
    public function isPost(): bool
    {
        return $this->method === self::METHOD_POST;
    }

    /**
     * Is this a PUT method request?
     *
     * @return bool
     */
    public function isPut(): bool
    {
        return $this->method === self::METHOD_PUT;
    }

    /**
     * Is this a DELETE method request?
     *
     * @return bool
     */
    public function isDelete(): bool
    {
        return $this->method === self::METHOD_DELETE;
    }

    /**
     * Is this a TRACE method request?
     *
     * @return bool
     */
    public function isTrace(): bool
    {
        return $this->method === self::METHOD_TRACE;
    }

    /**
     * Is this a CONNECT method request?
     *
     * @return bool
     */
    public function isConnect(): bool
    {
        return $this->method === self::METHOD_CONNECT;
    }

    /**
     * Is this a PATCH method request?
     *
     * @return bool
     */
    public function isPatch(): bool
    {
        return $this->method === self::METHOD_PATCH;
    }

    /**
     * Is the request a Javascript XMLHttpRequest?
     *
     * Should work with Prototype/Script.aculo.us, possibly others.
     *
     * @return bool
     */
    public function isXmlHttpRequest(): bool
    {
        $header = $this->getHeaders()->get(name: 'X_REQUESTED_WITH');
        return false !== $header && $header->getFieldValue() === 'XMLHttpRequest';
    }

    /**
     * Is this a Flash request?
     *
     * @return bool
     */
    public function isFlashRequest(): bool
    {
        $header = $this->getHeaders()->get(name: 'USER_AGENT');
        return false !== $header && stristr(haystack: $header->getFieldValue(), needle: ' flash');
    }

    /**
     * Return the formatted request line (first line) for this http request
     *
     * @return string
     */
    public function renderRequestLine(): string
    {
        return $this->method . ' ' . (string)$this->uri . ' HTTP/' . $this->version;
    }

    /**
     * @return string
     */
    public function toString(): string
    {
        $str = $this->renderRequestLine() . "\r\n";
        $str .= $this->getHeaders()->toString();
        $str .= "\r\n";
        $str .= $this->getContent();
        return $str;
    }

    /**
     * @return bool
     */
    public function getAllowCustomMethods(): bool
    {
        return $this->allowCustomMethods;
    }

    /**
     * @param bool $strictMethods
     */
    public function setAllowCustomMethods(bool $strictMethods): void
    {
        $this->allowCustomMethods = $strictMethods;
    }
}
