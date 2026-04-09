<?php

namespace Jield\Http\Client\Adapter;

use Jield\Http\Response;
use Jield\Stdlib\ArrayUtils;
use Laminas\Uri\Uri;
use Traversable;

use function count;
use function gettype;
use function is_array;
use function is_string;
use function strtolower;

/**
 * A testing-purposes adapter.
 *
 * Should be used to test all components that rely on Jield\Http\Client,
 * without actually performing an HTTP request. You should instantiate this
 * object manually, and then set it as the client's adapter. Then, you can
 * set the expected response using the setResponse() method.
 */
class Test implements AdapterInterface
{
    /**
     * Parameters array
     *
     * @var array
     */
    protected array $config = [];

    /**
     * Buffer of responses to be returned by the read() method.  Can be
     * set using setResponse() and addResponse().
     *
     * @var array
     */
    protected array $responses = ["HTTP/1.1 400 Bad Request\r\n\r\n"];

    /**
     * Current position in the response buffer
     *
     * @var int
     */
    protected int $responseIndex = 0;

    /**
     * Whether or not the next request will fail with an exception
     *
     * @var bool
     */
    protected bool $nextRequestWillFail = false;

    /**
     * Adapter constructor, currently empty. Config is set using setOptions()
     */
    public function __construct()
    {
    }

    /**
     * Set the nextRequestWillFail flag
     *
     * @param bool $flag
     * @return Test
     */
    public function setNextRequestWillFail(bool $flag): static
    {
        $this->nextRequestWillFail = $flag;

        return $this;
    }

    /**
     * Set the configuration array for the adapter
     *
     * @param array $options
     * @throws Exception\InvalidArgumentException
     */
    public function setOptions(array $options = []): void
    {
        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray(iterator: $options);
        }

        if (! is_array(value: $options)) {
            throw new Exception\InvalidArgumentException(
                message: 'Array or Traversable object expected, got ' . gettype(value: $options)
            );
        }

        foreach ($options as $k => $v) {
            $this->config[strtolower(string: $k)] = $v;
        }
    }

    /**
     * Connect to the remote server
     *
     * @param string $host
     * @param int $port
     * @param bool $secure
     * @throws Exception\RuntimeException
     */
    public function connect(string $host, int $port = 80, bool $secure = false): void
    {
        if ($this->nextRequestWillFail) {
            $this->nextRequestWillFail = false;
            throw new Exception\RuntimeException(message: 'Request failed');
        }
    }

    /**
     * Send request to the remote server
     *
     * @param string $method
     * @param Uri $url
     * @param string $httpVer
     * @param array $headers
     * @param string $body
     * @return string Request as string
     */
    public function write(string $method, Uri $url, string $httpVer = '1.1', array $headers = [], string $body = ''): string
    {
        // Build request headers
        $path = $url->getPath();
        if (empty($path)) {
            $path = '/';
        }
        $query   = $url->getQuery();
        $path   .= $query ? '?' . $query : '';
        $request = $method . ' ' . $path . ' HTTP/' . $httpVer . "\r\n";
        foreach ($headers as $k => $v) {
            if (is_string(value: $k)) {
                $v = $k . ': ' . $v;
            }
            $request .= $v . "\r\n";
        }

        // Add the request body
        $request .= "\r\n" . $body;

        // Do nothing - just return the request as string

        return $request;
    }

    /**
     * Return the response set in $this->setResponse()
     *
     * @return string
     */
    public function read(): string
    {
        if ($this->responseIndex >= count(value: $this->responses)) {
            $this->responseIndex = 0;
        }
        return $this->responses[$this->responseIndex++];
    }

    /**
     * Close the connection (dummy)
     */
    public function close(): void
    {
    }

    /**
     * Set the HTTP response(s) to be returned by this adapter
     *
     * @param array|string|Response $response
     */
    public function setResponse(array|string|Response $response): void
    {
        if ($response instanceof Response) {
            $response = $response->toString();
        }

        $this->responses     = (array) $response;
        $this->responseIndex = 0;
    }

    /**
     * Add another response to the response buffer.
     *
     * @param string|Response $response
     */
    public function addResponse(string|Response $response): void
    {
        if ($response instanceof Response) {
            $response = $response->toString();
        }

        $this->responses[] = $response;
    }

    /**
     * Sets the position of the response buffer.  Selects which
     * response will be returned on the next call to read().
     *
     * @param int $index
     * @throws Exception\OutOfRangeException
     */
    public function setResponseIndex(int $index): void
    {
        if ($index < 0 || $index >= count(value: $this->responses)) {
            throw new Exception\OutOfRangeException(
                message: 'Index out of range of response buffer size'
            );
        }
        $this->responseIndex = $index;
    }
}
