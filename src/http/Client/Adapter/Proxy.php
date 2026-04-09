<?php

namespace Jield\Http\Client\Adapter;

use Jield\Http\Client;
use Jield\Http\Client\Adapter\Exception as AdapterException;
use Jield\Http\Response;
use Jield\Stdlib\ArrayUtils;
use Jield\Stdlib\ErrorHandler;
use Laminas\Uri\Uri;
use Traversable;

use function base64_encode;
use function fgets;
use function fwrite;
use function gettype;
use function is_array;
use function is_resource;
use function is_string;
use function preg_match;
use function rtrim;
use function sprintf;
use function stream_context_set_option;
use function stream_copy_to_stream;
use function strlen;
use function strpos;
use function strtolower;
use function substr;

/**
 * HTTP Proxy-supporting Jield\Http\Client adapter class, based on the default
 * socket based adapter.
 *
 * Should be used if proxy HTTP access is required. If no proxy is set, will
 * fall back to Jield\Http\Client\Adapter\Socket behavior. Just like the
 * default Socket adapter, this adapter does not require any special extensions
 * installed.
 */
class Proxy extends Socket
{
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
        'proxy_host'         => '',
        'proxy_port'         => 8080,
        'proxy_user'         => '',
        'proxy_pass'         => '',
        'proxy_auth'         => Client::AUTH_BASIC,
    ];

    /**
     * Whether HTTPS CONNECT was already negotiated with the proxy or not
     *
     * @var bool
     */
    protected bool $negotiated = false;

    /**
     * Set the configuration array for the adapter
     *
     * @param array $options
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

        //enforcing that the proxy keys are set in the form proxy_*
        foreach ($options as $k => $v) {
            if (preg_match(pattern: '/^proxy[a-z]+/', subject: $k)) {
                $options['proxy_' . substr(string: $k, offset: 5, length: strlen(string: $k))] = $v;
                unset($options[$k]);
            }
        }

        parent::setOptions(options: $options);
    }

    /**
     * Connect to the remote server
     *
     * Will try to connect to the proxy server. If no proxy was set, will
     * fall back to the target server (behave like regular Socket adapter)
     *
     * @param string $host
     * @param int $port
     * @param bool $secure
     * @throws AdapterException\RuntimeException
     */
    public function connect(string $host, int $port = 80, bool $secure = false): void
    {
        // If no proxy is set, fall back to Socket adapter
        if (! $this->config['proxy_host']) {
            parent::connect(host: $host, port: $port, secure: $secure);
            return;
        }

        /* Url might require stream context even if proxy connection doesn't */
        if ($secure) {
            $this->config['sslusecontext'] = true;
            $this->setSslCryptoMethod      = false;
        }

        // Connect (a non-secure connection) to the proxy server
        parent::connect(
            host: $this->config['proxy_host'],
            port: $this->config['proxy_port'],
            secure: false
        );
    }

    /**
     * Send request to the proxy server
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
        // If no proxy is set, fall back to default Socket adapter
        if (! $this->config['proxy_host']) {
            return parent::write(method: $method, uri: $url, httpVer: $httpVer, headers: $headers, body: $body);
        }

        // Make sure we're properly connected
        if (! $this->socket) {
            throw new AdapterException\RuntimeException(message: 'Trying to write but we are not connected');
        }

        $host = $this->config['proxy_host'];
        $port = $this->config['proxy_port'];

        $isSecure      = strtolower(string: $url->getScheme()) === 'https';
        $connectedHost = ($isSecure ? $this->config['ssltransport'] : 'tcp') . '://' . $host;

        if ($this->connectedTo[1] !== $port || $this->connectedTo[0] !== $connectedHost) {
            throw new AdapterException\RuntimeException(
                message: 'Trying to write but we are connected to the wrong proxy server'
            );
        }

        // Add Proxy-Authorization header
        if ($this->config['proxy_user'] && ! isset($headers['proxy-authorization'])) {
            $headers['proxy-authorization'] = Client::encodeAuthHeader(
                user: $this->config['proxy_user'],
                password: $this->config['proxy_pass'],
                type: $this->config['proxy_auth']
            );
        }

        // if we are proxying HTTPS, preform CONNECT handshake with the proxy
        if ($isSecure && ! $this->negotiated) {
            $this->connectHandshake(host: $url->getHost(), port: $url->getPort(), httpVer: $httpVer, headers: $headers);
            $this->negotiated = true;
        }

        // Save request method for later
        $this->method = $method;

        if ($url->getUserInfo()) {
            $headers['Authorization'] = 'Basic ' . base64_encode(string: $url->getUserInfo());
        }

        $path  = $url->getPath();
        $query = $url->getQuery();
        $path .= $query ? '?' . $query : '';

        if (! $this->negotiated) {
            $path = $url->getScheme() . '://' . $url->getHost() . $path;
        }

        // Build request headers
        $request = sprintf('%s %s HTTP/%s%s', $method, $path, $httpVer, "\r\n");

        // Add all headers to the request string
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
        if ($test === false) {
            throw new AdapterException\RuntimeException(message: 'Error writing request to proxy server', code: 0, previous: $error);
        }

        if (is_resource(value: $body) && stream_copy_to_stream(from: $body, to: $this->socket) === 0) {
            throw new AdapterException\RuntimeException(message: 'Error writing request to server');
        }

        return $request;
    }

    /**
     * Preform handshaking with HTTPS proxy using CONNECT method
     *
     * @param string $host
     * @param int $port
     * @param string $httpVer
     * @throws AdapterException\RuntimeException
     */
    protected function connectHandshake(string $host, int $port = 443, string $httpVer = '1.1', array &$headers = []): void
    {
        $request = 'CONNECT ' . $host . ':' . $port . ' HTTP/' . $httpVer . "\r\n"
            . 'Host: ' . $host . "\r\n";

        // Add the user-agent header
        if (isset($this->config['useragent'])) {
            $request .= 'User-agent: ' . $this->config['useragent'] . "\r\n";
        }

        // If the proxy-authorization header is set, send it to proxy but remove
        // it from headers sent to target host
        if (isset($headers['proxy-authorization'])) {
            $request .= 'Proxy-authorization: ' . $headers['proxy-authorization'] . "\r\n";
            unset($headers['proxy-authorization']);
        }

        $request .= "\r\n";

        // Send the request
        ErrorHandler::start();
        $test  = fwrite(stream: $this->socket, data: $request);
        $error = ErrorHandler::stop();
        if (! $test) {
            throw new AdapterException\RuntimeException(message: 'Error writing request to proxy server', code: 0, previous: $error);
        }

        // Read response headers only
        $response  = '';
        $gotStatus = false;
        ErrorHandler::start();
        while ($line = fgets(stream: $this->socket)) {
            $gotStatus = $gotStatus || (strpos(haystack: $line, needle: 'HTTP') !== false);
            if ($gotStatus) {
                $response .= $line;
                if (! rtrim(string: $line)) {
                    break;
                }
            }
        }
        ErrorHandler::stop();

        // Check that the response from the proxy is 200
        if (Response::fromString(string: $response)->getStatusCode() !== 200) {
            throw new AdapterException\RuntimeException(message: sprintf(
                'Unable to connect to HTTPS proxy. Server response: %s',
                $response
            ));
        }

        // provide hostname to ssl for SNI
        $context = $this->getStreamContext();
        stream_context_set_option($context, 'ssl', 'peer_name', $host);

        try {
            $this->enableCryptoTransport(sslTransport: $this->config['ssltransport'], socket: $this->socket, host: $host);
        } catch (AdapterException\RuntimeException $e) {
            throw new AdapterException\RuntimeException(
                message: 'Unable to connect to HTTPS server through proxy: could not negotiate secure connection.',
                code: 0,
                previous: $e
            );
        }
    }

    /**
     * Close the connection to the server
     */
    public function close(): void
    {
        parent::close();
        $this->negotiated = false;
    }

    /**
     * Destructor: make sure the socket is disconnected
     */
    public function __destruct()
    {
        if ($this->socket) {
            $this->close();
        }
    }
}
