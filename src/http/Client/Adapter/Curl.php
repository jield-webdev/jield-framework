<?php

namespace Jield\Http\Client\Adapter;

use Jield\Http\Client\Adapter\AdapterInterface as HttpAdapter;
use Jield\Http\Client\Adapter\Exception as AdapterException;
use Jield\Stdlib\ArrayUtils;
use Laminas\Uri\Uri;
use Traversable;

use function array_key_exists;
use function base64_decode;
use function curl_close;
use function curl_errno;
use function curl_error;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt;
use function defined;
use function extension_loaded;
use function gettype;
use function in_array;
use function intval;
use function is_array;
use function is_float;
use function is_numeric;
use function is_resource;
use function number_format;
use function preg_match;
use function preg_replace;
use function preg_split;
use function sprintf;
use function str_replace;
use function strlen;
use function strtolower;
use function substr;
use function substr_replace;

use const CURL_HTTP_VERSION_1_0;
use const CURL_HTTP_VERSION_1_1;
use const CURLAUTH_BASIC;
use const CURLINFO_HEADER_OUT;
use const CURLINFO_HEADER_SIZE;
use const CURLOPT_CAINFO;
use const CURLOPT_CAPATH;
use const CURLOPT_CONNECTTIMEOUT;
use const CURLOPT_CONNECTTIMEOUT_MS;
use const CURLOPT_CUSTOMREQUEST;
use const CURLOPT_ENCODING;
use const CURLOPT_FILE;
use const CURLOPT_HEADER;
use const CURLOPT_HEADERFUNCTION;
use const CURLOPT_HTTP_VERSION;
use const CURLOPT_HTTPAUTH;
use const CURLOPT_HTTPGET;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_INFILE;
use const CURLOPT_INFILESIZE;
use const CURLOPT_MAXREDIRS;
use const CURLOPT_NOBODY;
use const CURLOPT_PORT;
use const CURLOPT_POST;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_PROXY;
use const CURLOPT_PROXYPORT;
use const CURLOPT_PROXYUSERPWD;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_SSL_VERIFYPEER;
use const CURLOPT_SSLCERT;
use const CURLOPT_SSLCERTPASSWD;
use const CURLOPT_TIMEOUT;
use const CURLOPT_TIMEOUT_MS;
use const CURLOPT_UPLOAD;
use const CURLOPT_URL;
use const CURLOPT_USERPWD;

/**
 * An adapter class for Jield\Http\Client based on the curl extension.
 * Curl requires libcurl. See for full requirements the PHP manual: http://php.net/curl
 */
class Curl implements HttpAdapter, StreamInterface
{
    /**
     * Operation timeout.
     *
     * @var int
     */
    public const int ERROR_OPERATION_TIMEDOUT = 28;

    /**
     * Parameters array
     *
     * @var array
     */
    protected array $config = [];

    /**
     * What host/port are we connected to?
     *
     * @var array
     */
    protected array $connectedTo = [null, null];

    /**
     * The curl session handle
     *
     * @var resource|null
     */
    protected $curl;

    /**
     * List of cURL options that should never be overwritten
     *
     * @var array
     */
    protected array $invalidOverwritableCurlOptions;

    /**
     * Response gotten from server
     *
     * @var string
     */
    protected string $response;

    /**
     * Stream for storing output
     *
     * @var resource
     */
    protected $outputStream;

    /**
     * Adapter constructor
     *
     * Config is set using setOptions()
     *
     * @throws AdapterException\InitializationException
     */
    public function __construct()
    {
        if (! extension_loaded(extension: 'curl')) {
            throw new AdapterException\InitializationException(
                message: 'cURL extension has to be loaded to use this Jield\Http\Client adapter'
            );
        }
        $this->invalidOverwritableCurlOptions = [
            CURLOPT_HTTPGET,
            CURLOPT_POST,
            CURLOPT_UPLOAD,
            CURLOPT_CUSTOMREQUEST,
            CURLOPT_HEADER,
            CURLOPT_RETURNTRANSFER,
            CURLOPT_HTTPHEADER,
            CURLOPT_INFILE,
            CURLOPT_INFILESIZE,
            CURLOPT_PORT,
            CURLOPT_MAXREDIRS,
            CURLOPT_CONNECTTIMEOUT,
        ];
    }

    /**
     * Set the configuration array for the adapter
     *
     * @param array $options
     * @return void
     * @throws AdapterException\InvalidArgumentException
     */
    public function setOptions(array $options = []): void
    {
        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray(iterator: $options);
        }
        if (! is_array(value: $options)) {
            throw new AdapterException\InvalidArgumentException(message: sprintf(
                'Array or Traversable object expected, got %s',
                gettype(value: $options)
            ));
        }

        /** Config Key Normalization */
        foreach ($options as $k => $v) {
            unset($options[$k]); // unset original value
            $options[str_replace(search: ['-', '_', ' ', '.'], replace: '', subject: strtolower(string: $k))] = $v; // replace w/ normalized
        }

        if (isset($options['proxyuser']) && isset($options['proxypass'])) {
            $this->setCurlOption(option: CURLOPT_PROXYUSERPWD, value: $options['proxyuser'] . ':' . $options['proxypass']);
            unset($options['proxyuser'], $options['proxypass']);
        }

        if (isset($options['sslverifypeer'])) {
            $this->setCurlOption(option: CURLOPT_SSL_VERIFYPEER, value: $options['sslverifypeer']);
            unset($options['sslverifypeer']);
        }

        foreach ($options as $k => $v) {
            $option = strtolower(string: $k);
            switch ($option) {
                case 'proxyhost':
                    $this->setCurlOption(option: CURLOPT_PROXY, value: $v);
                    break;
                case 'proxyport':
                    $this->setCurlOption(option: CURLOPT_PROXYPORT, value: $v);
                    break;
                default:
                    if (is_array(value: $v) && isset($this->config[$option]) && is_array(value: $this->config[$option])) {
                        $v = ArrayUtils::merge(a: $this->config[$option], b: $v, preserveNumericKeys: true);
                    }
                    $this->config[$option] = $v;
                    break;
            }
        }

        return $this;
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
     * Direct setter for cURL adapter related options.
     *
     * @param int|string $option
     * @param  mixed $value
     * @return $this
     */
    public function setCurlOption(int|string $option, mixed $value): static
    {
        if (! isset($this->config['curloptions'])) {
            $this->config['curloptions'] = [];
        }
        $this->config['curloptions'][$option] = $value;
        return $this;
    }

    /**
     * Initialize curl
     *
     * @param string $host
     * @param int $port
     * @param bool $secure
     * @return void
     * @throws AdapterException\RuntimeException If unable to connect.
     */
    public function connect(string $host, int $port = 80, bool $secure = false): void
    {
        // If we're already connected, disconnect first
        if ($this->curl) {
            $this->close();
        }

        // Do the actual connection
        $this->curl = curl_init();
        if ($port !== 80) {
            curl_setopt(handle: $this->curl, option: CURLOPT_PORT, value: $port);
        }

        if (isset($this->config['connecttimeout'])) {
            $connectTimeout = $this->config['connecttimeout'];
        } elseif (isset($this->config['timeout'])) {
            $connectTimeout = $this->config['timeout'];
        } else {
            $connectTimeout = null;
        }

        if ($connectTimeout !== null && ! is_numeric(value: $connectTimeout)) {
            throw new AdapterException\InvalidArgumentException(message: sprintf(
                'integer or numeric string expected, got %s',
                gettype(value: $connectTimeout)
            ));
        }

        if ($connectTimeout !== null) {
            $connectTimeout = (int) $connectTimeout;
        }

        if ($connectTimeout !== null) {
            if (defined(constant_name: 'CURLOPT_CONNECTTIMEOUT_MS')) {
                curl_setopt(handle: $this->curl, option: CURLOPT_CONNECTTIMEOUT_MS, value: $connectTimeout * 1000);
            } else {
                curl_setopt(handle: $this->curl, option: CURLOPT_CONNECTTIMEOUT, value: $connectTimeout);
            }
        }

        if (isset($this->config['timeout'])) {
            if (defined(constant_name: 'CURLOPT_TIMEOUT_MS')) {
                curl_setopt(handle: $this->curl, option: CURLOPT_TIMEOUT_MS, value: $this->config['timeout'] * 1000);
            } else {
                curl_setopt(handle: $this->curl, option: CURLOPT_TIMEOUT, value: $this->config['timeout']);
            }
        }

        if (isset($this->config['sslcafile']) && $this->config['sslcafile']) {
            curl_setopt(handle: $this->curl, option: CURLOPT_CAINFO, value: $this->config['sslcafile']);
        }
        if (isset($this->config['sslcapath']) && $this->config['sslcapath']) {
            curl_setopt(handle: $this->curl, option: CURLOPT_CAPATH, value: $this->config['sslcapath']);
        }

        if (isset($this->config['maxredirects'])) {
            // Set Max redirects
            curl_setopt(handle: $this->curl, option: CURLOPT_MAXREDIRS, value: $this->config['maxredirects']);
        }

        if (! $this->curl) {
            $this->close();

            throw new AdapterException\RuntimeException(message: 'Unable to Connect to ' . $host . ':' . $port);
        }

        if ($secure !== false) {
            // Behave the same like Jield\Http\Adapter\Socket on SSL options.
            if (isset($this->config['sslcert'])) {
                curl_setopt(handle: $this->curl, option: CURLOPT_SSLCERT, value: $this->config['sslcert']);
            }
            if (isset($this->config['sslpassphrase'])) {
                curl_setopt(handle: $this->curl, option: CURLOPT_SSLCERTPASSWD, value: $this->config['sslpassphrase']);
            }
        }

        // Update connected_to
        $this->connectedTo = [$host, $port];
    }

    /**
     * Send request to the remote server
     *
     * @param string $method
     * @param Uri $url
     * @param string $httpVer
     * @param array $headers
     * @param string $body
     * @return string        $request
     * @throws AdapterException\RuntimeException If connection fails, connected
     *     to wrong host, no PUT file defined, unsupported method, or unsupported
     *     cURL option.
     * @throws AdapterException\InvalidArgumentException If $method is currently not supported.
     * @throws AdapterException\TimeoutException If connection timed out.
     */
    public function write(string $method, Uri $url, string $httpVer = '1.1', array $headers = [], string $body = ''): string
    {
        if (is_float(value: $httpVer)) {
            $httpVer = number_format(num: $httpVer, decimals: 1, decimal_separator: '.', thousands_separator: '');
        }

        // Make sure we're properly connected
        if (! $this->curl) {
            throw new AdapterException\RuntimeException(message: 'Trying to write but we are not connected');
        }

        if ($this->connectedTo[0] !== $url->getHost() || $this->connectedTo[1] !== $url->getPort()) {
            throw new AdapterException\RuntimeException(message: 'Trying to write but we are connected to the wrong host');
        }

        // set URL
        curl_setopt(handle: $this->curl, option: CURLOPT_URL, value: $url->__toString());

        // ensure correct curl call
        $curlValue = true;
        switch ($method) {
            case 'GET':
                $curlMethod = CURLOPT_HTTPGET;
                break;

            case 'POST':
                $curlMethod = CURLOPT_POST;
                break;

            case 'PUT':
                // There are two different types of PUT request, either a Raw Data string has been set
                // or CURLOPT_INFILE and CURLOPT_INFILESIZE are used.
                if (is_resource(value: $body)) {
                    $this->config['curloptions'][CURLOPT_INFILE] = $body;
                }
                if (isset($this->config['curloptions'][CURLOPT_INFILE])) {
                    // Now we will probably already have Content-Length set, so that we have to delete it
                    // from $headers at this point:
                    if (
                        ! isset($headers['Content-Length'])
                        && ! isset($this->config['curloptions'][CURLOPT_INFILESIZE])
                    ) {
                        throw new AdapterException\RuntimeException(
                            message: 'Cannot set a file-handle for cURL option CURLOPT_INFILE'
                            . ' without also setting its size in CURLOPT_INFILESIZE.'
                        );
                    }

                    if (isset($headers['Content-Length'])) {
                        $this->config['curloptions'][CURLOPT_INFILESIZE] = (int) $headers['Content-Length'];
                        unset($headers['Content-Length']);
                    }

                    if (is_resource(value: $body)) {
                        $body = '';
                    }

                    $curlMethod = CURLOPT_UPLOAD;
                } else {
                    $curlMethod = CURLOPT_CUSTOMREQUEST;
                    $curlValue  = 'PUT';
                }
                break;

            case 'PATCH':
                $curlMethod = CURLOPT_CUSTOMREQUEST;
                $curlValue  = 'PATCH';
                break;

            case 'DELETE':
                $curlMethod = CURLOPT_CUSTOMREQUEST;
                $curlValue  = 'DELETE';
                break;

            case 'OPTIONS':
                $curlMethod = CURLOPT_CUSTOMREQUEST;
                $curlValue  = 'OPTIONS';
                break;

            case 'TRACE':
                $curlMethod = CURLOPT_CUSTOMREQUEST;
                $curlValue  = 'TRACE';
                break;

            case 'HEAD':
                $curlMethod = CURLOPT_CUSTOMREQUEST;
                $curlValue  = 'HEAD';
                break;

            default:
                // For now, through an exception for unsupported request methods
                throw new AdapterException\InvalidArgumentException(message: sprintf(
                    'Method \'%s\' currently not supported',
                    $method
                ));
        }

        if (is_resource(value: $body) && $curlMethod !== CURLOPT_UPLOAD) {
            throw new AdapterException\RuntimeException(message: 'Streaming requests are allowed only with PUT');
        }

        // get http version to use
        $curlHttp = $httpVer === '1.1' ? CURL_HTTP_VERSION_1_1 : CURL_HTTP_VERSION_1_0;

        // mark as HTTP request and set HTTP method
        curl_setopt(handle: $this->curl, option: CURLOPT_HTTP_VERSION, value: $curlHttp);
        curl_setopt(handle: $this->curl, option: $curlMethod, value: $curlValue);

        // Set the CURLOPT_NOBODY flag for HEAD HTTP method
        curl_setopt(handle: $this->curl, option: CURLOPT_NOBODY, value: $curlMethod === CURLOPT_CUSTOMREQUEST && $curlValue === 'HEAD');

        // Set the CURLINFO_HEADER_OUT flag so that we can retrieve the full request string later
        curl_setopt(handle: $this->curl, option: CURLINFO_HEADER_OUT, value: true);

        if ($this->outputStream) {
            // headers will be read into the response
            curl_setopt(handle: $this->curl, option: CURLOPT_HEADER, value: false);
            curl_setopt(handle: $this->curl, option: CURLOPT_HEADERFUNCTION, value: [$this, 'readHeader']);
            // and data will be written into the file
            curl_setopt(handle: $this->curl, option: CURLOPT_FILE, value: $this->outputStream);
        } else {
            // ensure headers are also returned
            curl_setopt(handle: $this->curl, option: CURLOPT_HEADER, value: true);

            // ensure actual response is returned
            curl_setopt(handle: $this->curl, option: CURLOPT_RETURNTRANSFER, value: true);
        }

        // Treating basic auth headers in a special way
        if (array_key_exists(key: 'Authorization', array: $headers) && 'Basic' === substr(string: $headers['Authorization'], offset: 0, length: 5)) {
            curl_setopt(handle: $this->curl, option: CURLOPT_HTTPAUTH, value: CURLAUTH_BASIC);
            curl_setopt(handle: $this->curl, option: CURLOPT_USERPWD, value: base64_decode(string: substr(string: $headers['Authorization'], offset: 6)));
            unset($headers['Authorization']);
        }

        // set additional headers
        if (! isset($headers['Accept'])) {
            $headers['Accept'] = '';
        }
        $curlHeaders = [];
        foreach ($headers as $key => $value) {
            $curlHeaders[] = $key . ': ' . $value;
        }

        curl_setopt(handle: $this->curl, option: CURLOPT_HTTPHEADER, value: $curlHeaders);

        /**
         * Make sure POSTFIELDS is set after $curlMethod is set:
         *
         * @link http://de2.php.net/manual/en/function.curl-setopt.php#81161
         */
        if ($curlMethod === CURLOPT_UPLOAD) {
            // this covers a PUT by file-handle:
            // Make the setting of this options explicit (rather than setting it through the loop following a bit lower)
            // to group common functionality together.
            curl_setopt(handle: $this->curl, option: CURLOPT_INFILE, value: $this->config['curloptions'][CURLOPT_INFILE]);
            curl_setopt(handle: $this->curl, option: CURLOPT_INFILESIZE, value: $this->config['curloptions'][CURLOPT_INFILESIZE]);
            unset($this->config['curloptions'][CURLOPT_INFILE]);
            unset($this->config['curloptions'][CURLOPT_INFILESIZE]);
        } elseif (in_array(needle: $method, haystack: ['POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], strict: true)) {
            curl_setopt(handle: $this->curl, option: CURLOPT_POSTFIELDS, value: $body);
        }

        // set additional curl options
        if (isset($this->config['curloptions'])) {
            foreach ((array) $this->config['curloptions'] as $k => $v) {
                if (!in_array(needle: $k, haystack: $this->invalidOverwritableCurlOptions) && curl_setopt(handle: $this->curl, option: $k, value: $v) === false) {
                    throw new AdapterException\RuntimeException(message: sprintf(
                        'Unknown or erroreous cURL option "%s" set',
                        $k
                    ));
                }
            }
        }

        $this->response = '';

        // send the request

        $response = curl_exec(handle: $this->curl);
        // if we used streaming, headers are already there
        if (! is_resource(value: $this->outputStream)) {
            $this->response = $response;
        }

        $request  = curl_getinfo(handle: $this->curl, option: CURLINFO_HEADER_OUT);
        $request .= $body;

        if ($response === false || empty($this->response)) {
            if (curl_errno(handle: $this->curl) === static::ERROR_OPERATION_TIMEDOUT) {
                throw new AdapterException\TimeoutException(
                    message: 'Read timed out',
                    code: AdapterException\TimeoutException::READ_TIMEOUT
                );
            }
            throw new AdapterException\RuntimeException(message: sprintf(
                'Error in cURL request: %s',
                curl_error(handle: $this->curl)
            ));
        }

        // separating header from body because it is dangerous to accidentially replace strings in the body
        $responseHeaderSize = curl_getinfo(handle: $this->curl, option: CURLINFO_HEADER_SIZE);
        $responseHeaders    = substr(string: $this->response, offset: 0, length: $responseHeaderSize);

        // cURL automatically decodes chunked-messages, this means we have to
        // disallow the Jield\Http\Response to do it again.
        $responseHeaders = preg_replace(pattern: "/Transfer-Encoding:\s*chunked\\r\\n/i", replacement: '', subject: $responseHeaders);

        // cURL can automatically handle content encoding; prevent double-decoding from occurring
        if (
            isset($this->config['curloptions'][CURLOPT_ENCODING])
            && '' === $this->config['curloptions'][CURLOPT_ENCODING]
        ) {
            $responseHeaders = preg_replace(pattern: "/Content-Encoding:\s*gzip\\r\\n/i", replacement: '', subject: $responseHeaders);
        }

        // cURL automatically handles Proxy rewrites, remove the "HTTP/1.0 200 Connection established" string:
        $responseHeaders = preg_replace(
            pattern: "/HTTP\/1.[01]\s*200\s*Connection\s*established\\r\\n\\r\\n/",
            replacement: '',
            subject: $responseHeaders
        );

        // replace old header with new, cleaned up, header
        $this->response = substr_replace(string: $this->response, replace: $responseHeaders, offset: 0, length: $responseHeaderSize);

        // Eliminate multiple HTTP responses.
        do {
            $parts = preg_split(pattern: '|(?:\r?\n){2}|m', subject: $this->response, limit: 2);
            $again = false;

            if (isset($parts[1]) && preg_match(pattern: "|^HTTP/1\.[01](.*?)\r\n|mi", subject: $parts[1])) {
                $this->response = $parts[1];
                $again          = true;
            }
        } while ($again);

        return $request;
    }

    /**
     * Return read response from server
     *
     * @return string
     */
    public function read(): string
    {
        return $this->response;
    }

    /**
     * Close the connection to the server
     */
    public function close(): void
    {
        if (is_resource(value: $this->curl)) {
            curl_close(handle: $this->curl);
        }
        $this->curl        = null;
        $this->connectedTo = [null, null];
    }

    /**
     * Get cUrl Handle
     *
     * @return resource
     */
    public function getHandle()
    {
        return $this->curl;
    }

    /**
     * Set output stream for the response
     *
     * @param resource $stream
     * @return $this
     */
    public function setOutputStream($stream): static
    {
        $this->outputStream = $stream;
        return $this;
    }

    /**
     * Header reader function for CURL
     *
     * @param resource $curl
     * @param string $header
     * @return int
     */
    public function readHeader($curl, string $header): int
    {
        $this->response .= $header;
        return strlen(string: $header);
    }
}
