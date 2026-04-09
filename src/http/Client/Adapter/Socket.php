<?php

namespace Jield\Http\Client\Adapter;

use Jield\Http\Client\Adapter\AdapterInterface as HttpAdapter;
use Jield\Http\Client\Adapter\Exception as AdapterException;
use Jield\Http\Request;
use Jield\Http\Response;
use Jield\Stdlib\ArrayUtils;
use Jield\Stdlib\ErrorHandler;
use Laminas\Uri\Uri;
use Traversable;

use function count;
use function ctype_xdigit;
use function extension_loaded;
use function fclose;
use function feof;
use function fgets;
use function fread;
use function ftell;
use function fwrite;
use function get_resource_type;
use function gettype;
use function hexdec;
use function is_array;
use function is_dir;
use function is_file;
use function is_numeric;
use function is_resource;
use function is_string;
use function openssl_error_string;
use function rtrim;
use function sprintf;
use function str_ireplace;
use function stream_context_create;
use function stream_context_set_option;
use function stream_copy_to_stream;
use function stream_get_meta_data;
use function stream_set_timeout;
use function stream_socket_client;
use function stream_socket_enable_crypto;
use function strlen;
use function strpos;
use function strtolower;
use function substr;
use function trim;
use function version_compare;

use const PHP_VERSION;
use const STREAM_CLIENT_CONNECT;
use const STREAM_CLIENT_PERSISTENT;
use const STREAM_CRYPTO_METHOD_SSLv23_CLIENT;
use const STREAM_CRYPTO_METHOD_SSLv2_CLIENT;
use const STREAM_CRYPTO_METHOD_SSLv3_CLIENT;
use const STREAM_CRYPTO_METHOD_TLS_CLIENT;
use const STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT;
use const STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
use const STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;

/**
 * A sockets based (stream\socket\client) adapter class for Jield\Http\Client. Can be used
 * on almost every PHP environment, and does not require any special extensions.
 */
class Socket implements HttpAdapter, StreamInterface
{
    /**
     * Map SSL transport wrappers to stream crypto method constants
     *
     * @var array
     */
    protected static array $sslCryptoTypes = [
        'ssl'   => STREAM_CRYPTO_METHOD_SSLv23_CLIENT,
        'sslv2' => STREAM_CRYPTO_METHOD_SSLv2_CLIENT,
        'sslv3' => STREAM_CRYPTO_METHOD_SSLv3_CLIENT,
        'tls'   => STREAM_CRYPTO_METHOD_TLS_CLIENT,
    ];

    /**
     * The socket for server connection
     *
     * @var resource|null
     */
    protected $socket;

    /**
     * What host/port are we connected to?
     *
     * @var array
     */
    protected array $connectedTo = [null, null];

    /**
     * Stream for storing output
     *
     * @var resource
     */
    protected $outStream;

    /**
     * Parameters array
     *
     * @var array
     */
    protected array $config = [
        'persistent'         => false,
        'ssltransport'       => 'tls',
        'sslcert'            => null,
        'sslpassphrase'      => null,
        'sslverifypeer'      => true,
        'sslcafile'          => null,
        'sslcapath'          => null,
        'sslallowselfsigned' => false,
        'sslusecontext'      => false,
        'sslverifypeername'  => true,
    ];

    /**
     * Request method - will be set by write() and might be used by read()
     *
     * @var string
     */
    protected string $method;

    /**
     * Stream context
     *
     * @var resource
     */
    protected $context;

    /** @var bool */
    protected bool $setSslCryptoMethod = true;

    /**
     * Adapter constructor, currently empty. Config is set using setOptions()
     */
    public function __construct()
    {
    }

    /**
     * Set the configuration array for the adapter
     *
     * @param array $options
     * @throws AdapterException\InvalidArgumentException
     */
    public function setOptions(array $options = []): void
    {
        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray(iterator: $options);
        }
        if (! is_array(value: $options)) {
            throw new AdapterException\InvalidArgumentException(
                message: 'Array or Jield\Config object expected, got ' . gettype(value: $options)
            );
        }

        foreach ($options as $k => $v) {
            $this->config[strtolower(string: $k)] = $v;
        }
    }

    /**
     * Retrieve the array of all configuration options
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Set the stream context for the TCP connection to the server
     *
     * Can accept either a pre-existing stream context resource, or an array
     * of stream options, similar to the options array passed to the
     * stream_context_create() PHP function. In such case a new stream context
     * will be created using the passed options.
     *
     * @since  Jield 1.9
     * @param  mixed $context Stream context or array of context options
     * @throws Exception\InvalidArgumentException
     * @return $this
     */
    public function setStreamContext(mixed $context): static
    {
        if (is_resource(value: $context) && get_resource_type(resource: $context) === 'stream-context') {
            $this->context = $context;
        } elseif (is_array(value: $context)) {
            $this->context = stream_context_create(options: $context);
        } else {
            // Invalid parameter
            throw new AdapterException\InvalidArgumentException(message: sprintf(
                'Expecting either a stream context resource or array, got %s',
                gettype(value: $context)
            ));
        }

        return $this;
    }

    /**
     * Get the stream context for the TCP connection to the server.
     *
     * If no stream context is set, will create a default one.
     *
     * @return resource
     */
    public function getStreamContext()
    {
        if (! $this->context) {
            $this->context = stream_context_create();
        }

        return $this->context;
    }

    /**
     * Connect to the remote server
     *
     * @param string $host
     * @param int $port
     * @param bool $secure
     * @throws AdapterException\RuntimeException
     */
    public function connect(string $host, int $port = 80, bool $secure = false): void
    {
        // If we are connected to the wrong host, disconnect first
        $connectedTo   = $this->connectedTo[0] ?? '';
        $connectedHost = strpos(haystack: $connectedTo, needle: '://')
            ? substr(string: $connectedTo, offset: strpos(haystack: $connectedTo, needle: '://') + 3, length: strlen(string: $connectedTo))
            : $connectedTo;

        if ($connectedHost !== $host || $this->connectedTo[1] !== $port) {
            if (is_resource(value: $this->socket)) {
                $this->close();
            }
        }

        // Now, if we are not connected, connect
        if (! is_resource(value: $this->socket) || !$this->config['keepalive']) {
            $context = $this->getStreamContext();

            if ($secure || $this->config['sslusecontext']) {
                if (($this->config['sslverifypeer'] !== null) && !stream_context_set_option($context, 'ssl', 'verify_peer', $this->config['sslverifypeer'])) {
                    throw new AdapterException\RuntimeException(message: 'Unable to set sslverifypeer option');
                }

                if ($this->config['sslcafile'] && !stream_context_set_option($context, 'ssl', 'cafile', $this->config['sslcafile'])) {
                    throw new AdapterException\RuntimeException(message: 'Unable to set sslcafile option');
                }

                if ($this->config['sslcapath'] && !stream_context_set_option($context, 'ssl', 'capath', $this->config['sslcapath'])) {
                    throw new AdapterException\RuntimeException(message: 'Unable to set sslcapath option');
                }

                if (($this->config['sslallowselfsigned'] !== null) && !stream_context_set_option(
                        $context,
                        'ssl',
                        'allow_self_signed',
                        $this->config['sslallowselfsigned']
                    )) {
                        throw new AdapterException\RuntimeException(message: 'Unable to set sslallowselfsigned option');
                    }

                if (($this->config['sslcert'] !== null) && !stream_context_set_option($context, 'ssl', 'local_cert', $this->config['sslcert'])) {
                    throw new AdapterException\RuntimeException(message: 'Unable to set sslcert option');
                }

                if (($this->config['sslpassphrase'] !== null) && !stream_context_set_option($context, 'ssl', 'passphrase', $this->config['sslpassphrase'])) {
                    throw new AdapterException\RuntimeException(message: 'Unable to set sslpassphrase option');
                }

                if (($this->config['sslverifypeername'] !== null) && !stream_context_set_option(
                        $context,
                        'ssl',
                        'verify_peer_name',
                        $this->config['sslverifypeername']
                    )) {
                        throw new AdapterException\RuntimeException(message: 'Unable to set sslverifypeername option');
                    }
            }

            $flags = STREAM_CLIENT_CONNECT;
            if ($this->config['persistent']) {
                $flags |= STREAM_CLIENT_PERSISTENT;
            }

            if (isset($this->config['connecttimeout'])) {
                $connectTimeout = $this->config['connecttimeout'];
            } else {
                $connectTimeout = $this->config['timeout'];
            }

            if ($connectTimeout !== null && ! is_numeric(value: $connectTimeout)) {
                throw new AdapterException\InvalidArgumentException(message: sprintf(
                    'integer or numeric string expected, got %s',
                    gettype(value: $connectTimeout)
                ));
            }

            ErrorHandler::start();
            $this->socket = stream_socket_client(
                address: $host . ':' . $port,
                error_code: $errno,
                error_message: $errstr,
                timeout: (int) $connectTimeout,
                flags: $flags,
                context: $context
            );
            $error        = ErrorHandler::stop();

            if (! $this->socket) {
                $this->close();
                throw new AdapterException\RuntimeException(
                    message: sprintf(
                        'Unable to connect to %s:%d%s',
                        $host,
                        $port,
                        $error ? ' . Error #' . $error->getCode() . ': ' . $error->getMessage() : ''
                    ),
                    code: 0,
                    previous: $error
                );
            }

            // Set the stream timeout
            if (! stream_set_timeout(stream: $this->socket, seconds: (int)$this->config['timeout'])) {
                throw new AdapterException\RuntimeException(message: 'Unable to set the connection timeout');
            }

            if ($secure || $this->config['sslusecontext']) {
                if ($this->setSslCryptoMethod) {
                    try {
                        $this->enableCryptoTransport(sslTransport: $this->config['ssltransport'], socket: $this->socket, host: $host);
                    } catch (AdapterException\RuntimeException $e) {
                        $this->close();
                        throw $e;
                    }
                }

                $host = $this->config['ssltransport'] . '://' . $host;
            } else {
                $host = 'tcp://' . $host;
            }

            // Update connectedTo
            $this->connectedTo = [$host, $port];
        }
    }

    /**
     * @param string $sslTransport Transport name from $config['ssltransport']
     * @param resource $socket
     * @param string $host Host name used only for useful exception message
     */
    protected function enableCryptoTransport(string $sslTransport, $socket, string $host): void
    {
        $sslCryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (isset(static::$sslCryptoTypes[$sslTransport])) {
            $sslCryptoMethod = static::$sslCryptoTypes[$this->config['ssltransport']];
        }

        // Since php 5.6.7 and up to 7.2.0 constant means tls 1.0 only, expand back to all versions
        // We can do this because STREAM_CRYPTO_METHOD_TLS_ANY_CLIENT is available
        // in enum but not registered as php constant.
        // @see  https://github.com/php/php-src/blob/php-5.6.7/main/streams/php_stream_transport.h#L179
        if (
            version_compare(version1: PHP_VERSION, version2: '7.2.0', operator: '<')
            && $sslCryptoMethod === STREAM_CRYPTO_METHOD_TLS_CLIENT
        ) {
            $sslCryptoMethod  = STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT;
            $sslCryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
            $sslCryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }
        ErrorHandler::start();
        $test  = stream_socket_enable_crypto(stream: $socket, enable: true, crypto_method: $sslCryptoMethod);
        $error = ErrorHandler::stop();
        if (! $test || $error) {
            // Error handling is kind of difficult when it comes to SSL
            $errorString = '';
            if (extension_loaded(extension: 'openssl')) {
                while (($sslError = openssl_error_string()) !== false) {
                    $errorString .= sprintf('; SSL error: %s', $sslError);
                }
            }

            if ((! $errorString) && $this->config['sslverifypeer']) {
                // There's good chance our error is due to sslcapath not being properly set
                if (! ($this->config['sslcafile'] || $this->config['sslcapath'])) {
                    $errorString = 'make sure the "sslcafile" or "sslcapath" option are properly set for '
                        . 'the environment.';
                } elseif ($this->config['sslcafile'] && ! is_file(filename: $this->config['sslcafile'])) {
                    $errorString = 'make sure the "sslcafile" option points to a valid SSL certificate '
                        . 'file';
                } elseif ($this->config['sslcapath'] && ! is_dir(filename: $this->config['sslcapath'])) {
                    $errorString = 'make sure the "sslcapath" option points to a valid SSL certificate '
                        . 'directory';
                }
            }

            if ($errorString) {
                $errorString = sprintf(': %s', $errorString);
            }

            throw new AdapterException\RuntimeException(
                message: sprintf(
                    'Unable to enable crypto on TCP connection %s%s',
                    $host,
                    $errorString
                ),
                code: 0,
                previous: $error
            );
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
     *@throws AdapterException\RuntimeException
     */
    public function write(string $method, Uri $url, string $httpVer = '1.1', array $headers = [], string $body = ''): string
    {
        // Make sure we're properly connected
        if (! $this->socket) {
            throw new AdapterException\RuntimeException(message: 'Trying to write but we are not connected');
        }

        $host = $url->getHost();
        $host = (strtolower(string: $url->getScheme()) === 'https' ? $this->config['ssltransport'] : 'tcp') . '://' . $host;
        if ($this->connectedTo[0] !== $host || $this->connectedTo[1] !== $url->getPort()) {
            throw new AdapterException\RuntimeException(message: 'Trying to write but we are connected to the wrong host');
        }

        // Save request method for later
        $this->method = $method;

        // Build request headers
        $path    = $url->getPath();
        $query   = $url->getQuery();
        $path   .= $query ? '?' . $query : '';
        $request = $method . ' ' . $path . ' HTTP/' . $httpVer . "\r\n";
        foreach ($headers as $k => $v) {
            if (is_string(value: $k)) {
                $v = $k . ': ' . $v;
            }
            $request .= $v . "\r\n";
        }

        if (is_resource(value: $body)) {
            $request .= "\r\n";
        } else {
            // Add the request body
            $request .= "\r\n" . $body;
        }

        // Send the request
        ErrorHandler::start();
        $test  = fwrite(stream: $this->socket, data: $request);
        $error = ErrorHandler::stop();
        if (false === $test) {
            throw new AdapterException\RuntimeException(message: 'Error writing request to server', code: 0, previous: $error);
        }

        if (is_resource(value: $body) && stream_copy_to_stream(from: $body, to: $this->socket) === 0) {
            throw new AdapterException\RuntimeException(message: 'Error writing request to server');
        }

        return $request;
    }

    /**
     * Read response from server
     *
     * @throws AdapterException\RuntimeException
     * @return string
     */
    public function read(): string
    {
        // First, read headers only
        $response  = '';
        $gotStatus = false;

        while (($line = fgets(stream: $this->socket)) !== false) {
            $gotStatus = $gotStatus || (strpos(haystack: $line, needle: 'HTTP') !== false);
            if ($gotStatus) {
                $response .= $line;
                if (rtrim(string: $line) === '') {
                    break;
                }
            }
        }

        $this->_checkSocketReadTimeout();

        $responseObj = Response::fromString(string: $response);

        $statusCode = $responseObj->getStatusCode();

        // Handle 100 and 101 responses internally by restarting the read again
        if ($statusCode === 100 || $statusCode === 101) {
            return $this->read();
        }

        // Check headers to see what kind of connection / transfer encoding we have
        $headers = $responseObj->getHeaders();

        /**
         * Responses to HEAD requests and 204 or 304 responses are not expected
         * to have a body - stop reading here
         */
        if (
            $statusCode === 304
            || $statusCode === 204
            || $this->method === Request::METHOD_HEAD
        ) {
            // Close the connection if requested to do so by the server
            $connection = $headers->get(name: 'connection');
            if ($connection && $connection->getFieldValue() === 'close') {
                $this->close();
            }
            return $response;
        }

        // If we got a 'transfer-encoding: chunked' header
        $transferEncoding = $headers->get(name: 'transfer-encoding');
        $contentLength    = $headers->get(name: 'content-length');
        if ($transferEncoding !== false) {
            if (strtolower(string: $transferEncoding->getFieldValue()) === 'chunked') {
                do {
                    $line = fgets(stream: $this->socket);
                    $this->_checkSocketReadTimeout();

                    $chunk = $line;

                    // Figure out the next chunk size
                    $chunksize = trim(string: $line);
                    if (! ctype_xdigit($chunksize)) {
                        $this->close();
                        throw new AdapterException\RuntimeException(message: sprintf(
                            'Invalid chunk size "%s" unable to read chunked body',
                            $chunksize
                        ));
                    }

                    // Convert the hexadecimal value to plain integer
                    $chunksize = hexdec(hex_string: $chunksize);

                    // Read next chunk
                    $readTo = ftell(stream: $this->socket) + $chunksize;

                    do {
                        $currentPos = ftell(stream: $this->socket);
                        if ($currentPos >= $readTo) {
                            break;
                        }

                        if ($this->outStream) {
                            if (stream_copy_to_stream(from: $this->socket, to: $this->outStream, length: $readTo - $currentPos) === 0) {
                                $this->_checkSocketReadTimeout();
                                break;
                            }
                        } else {
                            $line = fread(stream: $this->socket, length: $readTo - $currentPos);
                            if ($line === false || strlen(string: $line) === 0) {
                                $this->_checkSocketReadTimeout();
                                break;
                            }
                            $chunk .= $line;
                        }
                    } while (! feof(stream: $this->socket));

                    ErrorHandler::start();
                    $chunk .= fgets(stream: $this->socket);
                    ErrorHandler::stop();
                    $this->_checkSocketReadTimeout();

                    if (! $this->outStream) {
                        $response .= $chunk;
                    }
                } while ($chunksize > 0);
            } else {
                $this->close();
                throw new AdapterException\RuntimeException(message: sprintf(
                    'Cannot handle "%s" transfer encoding',
                    $transferEncoding->getFieldValue()
                ));
            }

            // We automatically decode chunked-messages when writing to a stream
            // this means we have to disallow the Jield\Http\Response to do it again
            if ($this->outStream) {
                $response = str_ireplace(search: "Transfer-Encoding: chunked\r\n", replace: '', subject: $response);
            }
        // Else, if we got the content-length header, read this number of bytes
        } elseif ($contentLength !== false) {
            // If we got more than one Content-Length header (see Jield-9404) use
            // the last value sent
            if (is_array(value: $contentLength)) {
                $contentLength = $contentLength[count(value: $contentLength) - 1];
            }
            $contentLength = $contentLength->getFieldValue();

            $currentPos = ftell(stream: $this->socket);

            for (
                $readTo = $currentPos + $contentLength;
                 $readTo > $currentPos;
                 $currentPos = ftell(stream: $this->socket)
            ) {
                if ($this->outStream) {
                    if (stream_copy_to_stream(from: $this->socket, to: $this->outStream, length: $readTo - $currentPos) === 0) {
                        $this->_checkSocketReadTimeout();
                        break;
                    }
                } else {
                    $chunk = fread(stream: $this->socket, length: $readTo - $currentPos);
                    if ($chunk === false || strlen(string: $chunk) === 0) {
                        $this->_checkSocketReadTimeout();
                        break;
                    }

                    $response .= $chunk;
                }

                // Break if the connection ended prematurely
                if (feof(stream: $this->socket)) {
                    break;
                }
            }

        // Fallback: just read the response until EOF
        } else {
            do {
                if ($this->outStream) {
                    if (stream_copy_to_stream(from: $this->socket, to: $this->outStream) === 0) {
                        $this->_checkSocketReadTimeout();
                        break;
                    }
                } else {
                    $buff = fread(stream: $this->socket, length: 8192);
                    if ($buff === false || strlen(string: $buff) === 0) {
                        $this->_checkSocketReadTimeout();
                        break;
                    } else {
                        $response .= $buff;
                    }
                }
            } while (feof(stream: $this->socket) === false);

            $this->close();
        }

        // Close the connection if requested to do so by the server
        $connection = $headers->get(name: 'connection');
        if ($connection && $connection->getFieldValue() === 'close') {
            $this->close();
        }

        return $response;
    }

    /**
     * Close the connection to the server
     */
    public function close(): void
    {
        if (is_resource(value: $this->socket)) {
            ErrorHandler::start();
            fclose(stream: $this->socket);
            ErrorHandler::stop();
        }
        $this->socket      = null;
        $this->connectedTo = [null, null];
    }

    /**
     * Check if the socket has timed out - if so close connection and throw
     * an exception
     *
     * @throws AdapterException\TimeoutException with READ_TIMEOUT code
     */
    // @codingStandardsIgnoreStart
    protected function _checkSocketReadTimeout(): void
    {
        // @codingStandardsIgnoreEnd
        if ($this->socket) {
            $info     = stream_get_meta_data(stream: $this->socket);
            $timedout = $info['timed_out'];
            if ($timedout) {
                $this->close();
                throw new AdapterException\TimeoutException(
                    message: sprintf('Read timed out after %d seconds', $this->config['timeout']),
                    code: AdapterException\TimeoutException::READ_TIMEOUT
                );
            }
        }
    }

    /**
     * Set output stream for the response
     *
     * @param resource $stream
     * @return Socket
     */
    public function setOutputStream($stream): static
    {
        $this->outStream = $stream;
        return $this;
    }

    /**
     * Destructor: make sure the socket is disconnected
     *
     * If we are in persistent TCP mode, will not close the connection
     */
    public function __destruct()
    {
        if (!$this->config['persistent'] && $this->socket) {
            $this->close();
        }
    }
}
