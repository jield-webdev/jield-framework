<?php

namespace Jield\Http\PhpEnvironment;

use Jield\Http\Header\Cookie;
use Jield\Http\Request as HttpRequest;
use Jield\Stdlib\Parameters;
use Jield\Stdlib\ParametersInterface;
use Laminas\Uri\Http as HttpUri;
use Laminas\Validator\Hostname as HostnameValidator;

use function basename;
use function dirname;
use function file_get_contents;
use function function_exists;
use function is_array;
use function is_string;
use function preg_match;
use function preg_replace;
use function rtrim;
use function str_replace;
use function strlen;
use function strpos;
use function strrpos;
use function strtolower;
use function strtr;
use function substr;
use function trim;
use function ucfirst;
use function ucwords;

use const PHP_SAPI;

/**
 * HTTP Request for current PHP environment
 */
class Request extends HttpRequest
{
    /**
     * Base URL of the application.
     *
     * @var string
     */
    protected string $baseUrl;

    /**
     * Base Path of the application.
     *
     * @var string
     */
    protected string $basePath;

    /**
     * Actual request URI, independent of the platform.
     *
     * @var string
     */
    protected string $requestUri;

    /**
     * PHP server params ($_SERVER)
     *
     * @var ParametersInterface
     */
    protected ParametersInterface $serverParams;

    /**
     * PHP environment params ($_ENV)
     *
     * @var ParametersInterface
     */
    protected ParametersInterface $envParams;

    /**
     * Construct
     * Instantiates request.
     *
     * @param bool $allowCustomMethods
     */
    public function __construct(bool $allowCustomMethods = true)
    {
        $this->setAllowCustomMethods(strictMethods: $allowCustomMethods);

        $this->setEnv(env: new Parameters(values: $_ENV));

        if ($_GET) {
            $this->setQuery(query: new Parameters(values: $_GET));
        }
        if ($_POST) {
            $this->setPost(post: new Parameters(values: $_POST));
        }
        if ($_COOKIE) {
            $this->setCookies(cookie: new Parameters(values: $_COOKIE));
        }
        if ($_FILES) {
            // convert PHP $_FILES superglobal
            $files = $this->mapPhpFiles();
            $this->setFiles(files: new Parameters(values: $files));
        }

        $this->setServer(server: new Parameters(values: $_SERVER));
    }

    /**
     * Get raw request body
     *
     * @return string
     */
    public function getContent(): string
    {
        if (empty($this->content)) {
            $requestBody = file_get_contents(filename: 'php://input');
            if (strlen(string: $requestBody) > 0) {
                $this->content = $requestBody;
            }
        }

        return $this->content;
    }

    /**
     * Set cookies
     *
     * Instantiate and set cookies.
     *
     * @param string|array<string, string> $cookie
     * @return $this
     */
    public function setCookies(array|string $cookie): static
    {
        $this->getHeaders()->addHeader(header: new Cookie(array: (array) $cookie));
        return $this;
    }

    /**
     * Set the request URI.
     *
     * @param string $requestUri
     * @return $this
     */
    public function setRequestUri(string $requestUri): static
    {
        $this->requestUri = $requestUri;
        return $this;
    }

    /**
     * Get the request URI.
     *
     * @return string
     */
    public function getRequestUri(): string
    {
        if ($this->requestUri === null) {
            $this->requestUri = $this->detectRequestUri();
        }
        return $this->requestUri;
    }

    /**
     * Set the base URL.
     *
     * @param string $baseUrl
     * @return $this
     */
    public function setBaseUrl(string $baseUrl): static
    {
        $this->baseUrl = rtrim(string: $baseUrl, characters: '/');
        return $this;
    }

    /**
     * Get the base URL.
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        if ($this->baseUrl === null) {
            $this->setBaseUrl(baseUrl: $this->detectBaseUrl());
        }
        return $this->baseUrl;
    }

    /**
     * Set the base path.
     *
     * @param string $basePath
     * @return $this
     */
    public function setBasePath(string $basePath): static
    {
        $this->basePath = rtrim(string: $basePath, characters: '/');
        return $this;
    }

    /**
     * Get the base path.
     *
     * @return string
     */
    public function getBasePath(): string
    {
        if ($this->basePath === null) {
            $this->setBasePath(basePath: $this->detectBasePath());
        }

        return $this->basePath;
    }

    /**
     * Provide an alternate Parameter Container implementation for server parameters in this object,
     * (this is NOT the primary API for value setting, for that see getServer())
     *
     * @return $this
     */
    public function setServer(ParametersInterface $server): static
    {
        $this->serverParams = $server;

        // This seems to be the only way to get the Authorization header on Apache
        if (function_exists(function: 'apache_request_headers')) {
            $apacheRequestHeaders = apache_request_headers();
            if (! isset($this->serverParams['HTTP_AUTHORIZATION'])) {
                if (isset($apacheRequestHeaders['Authorization'])) {
                    $this->serverParams->set(name: 'HTTP_AUTHORIZATION', value: $apacheRequestHeaders['Authorization']);
                } elseif (isset($apacheRequestHeaders['authorization'])) {
                    $this->serverParams->set(name: 'HTTP_AUTHORIZATION', value: $apacheRequestHeaders['authorization']);
                }
            }
        }

        // set headers
        $headers = [];

        foreach ($server as $key => $value) {
            if ($value || (! is_array(value: $value) && strlen(string: $value ?? ''))) {
                if (strpos(haystack: $key, needle: 'HTTP_') === 0) {
                    if (strpos(haystack: $key, needle: 'HTTP_COOKIE') === 0) {
                        // Cookies are handled using the $_COOKIE superglobal
                        continue;
                    }

                    $headers[strtr(ucwords(string: strtolower(string: strtr(substr(string: $key, offset: 5), '_', ' '))), ' ', '-')] = $value;
                } elseif (strpos(haystack: $key, needle: 'CONTENT_') === 0) {
                    $name = substr(string: $key, offset: 8); // Remove "Content-"
                    $headers['Content-' . ($name === 'MD5' ? $name : ucfirst(string: strtolower(string: $name)))] = $value;
                }
            }
        }

        $this->getHeaders()->addHeaders(headers: $headers);

        // set method
        if (isset($this->serverParams['REQUEST_METHOD'])) {
            $this->setMethod(method: $this->serverParams['REQUEST_METHOD']);
        }

        // set HTTP version
        if (
            isset($this->serverParams['SERVER_PROTOCOL'])
            && strpos(haystack: $this->serverParams['SERVER_PROTOCOL'], needle: self::VERSION_10) !== false
        ) {
            $this->setVersion(version: self::VERSION_10);
        }

        // set URI
        $uri = new HttpUri();

        // URI scheme
        if (
            (! empty($this->serverParams['HTTPS']) && strtolower(string: $this->serverParams['HTTPS']) !== 'off')
            || (! empty($this->serverParams['HTTP_X_FORWARDED_PROTO'])
                 && $this->serverParams['HTTP_X_FORWARDED_PROTO'] === 'https')
        ) {
            $scheme = 'https';
        } else {
            $scheme = 'http';
        }
        $uri->setScheme(scheme: $scheme);

        // URI host & port
        $host = null;
        $port = null;

        // Set the host
        $headerHost = $this->getHeaders()->get(name: 'host');
        if ($headerHost) {
            $host = $headerHost->getFieldValue();

            // works for regname, IPv4 & IPv6
            if (preg_match(pattern: '|\:(\d+)$|', subject: $host, matches: $matches)) {
                $host = substr(string: $host, offset: 0, length: -1 * (strlen(string: $matches[1]) + 1));
                $port = (int) $matches[1];
            }

            // set up a validator that check if the hostname is legal (not spoofed)
            $hostnameValidator = new HostnameValidator(options: [
                'allow'       => HostnameValidator::ALLOW_ALL,
                'useIdnCheck' => false,
                'useTldCheck' => false,
            ]);
            // If invalid. Reset the host & port
            if (! $hostnameValidator->isValid(value: $host)) {
                $host = null;
                $port = null;
            }
        }

        if (! $host && isset($this->serverParams['SERVER_NAME'])) {
            $host = $this->serverParams['SERVER_NAME'];
            if (isset($this->serverParams['SERVER_PORT'])) {
                $port = (int) $this->serverParams['SERVER_PORT'];
            }
            // Check for missinterpreted IPv6-Address
            // Reported at least for Safari on Windows
            if (isset($this->serverParams['SERVER_ADDR']) && preg_match(pattern: '/^\[[0-9a-fA-F\:]+\]$/', subject: $host)) {
                $host = '[' . $this->serverParams['SERVER_ADDR'] . ']';
                if ($port . ']' === substr(string: $host, offset: strrpos(haystack: $host, needle: ':') + 1)) {
                    // The last digit of the IPv6-Address has been taken as port
                    // Unset the port so the default port can be used
                    $port = null;
                }
            }
        }
        $uri->setHost(host: $host);
        $uri->setPort(port: $port);

        // URI path
        $requestUri = $this->getRequestUri();
        if (($qpos = strpos(haystack: $requestUri, needle: '?')) !== false) {
            $requestUri = substr(string: $requestUri, offset: 0, length: $qpos);
        }

        $uri->setPath(path: $requestUri);

        // URI query
        if (isset($this->serverParams['QUERY_STRING'])) {
            $uri->setQuery(query: $this->serverParams['QUERY_STRING']);
        }

        $this->setUri(uri: $uri);

        return $this;
    }

    /**
     * Return the parameter container responsible for server parameters or a single parameter value.
     *
     * @see http://www.faqs.org/rfcs/rfc3875.html
     *
     * @param string|null $name            Parameter name to retrieve, or null to get the whole container.
     * @param mixed|null $default         Default value to use when the parameter is missing.
     * @return ParametersInterface|mixed
     */
    public function getServer(?string $name = null, mixed $default = null): mixed
    {
        if ($this->serverParams === null) {
            $this->serverParams = new Parameters();
        }

        if ($name === null) {
            return $this->serverParams;
        }

        return $this->serverParams->get(name: $name, default: $default);
    }

    /**
     * Provide an alternate Parameter Container implementation for env parameters in this object,
     * (this is NOT the primary API for value setting, for that see env())
     *
     * @return $this
     */
    public function setEnv(ParametersInterface $env): static
    {
        $this->envParams = $env;
        return $this;
    }

    /**
     * Return the parameter container responsible for env parameters or a single parameter value.
     *
     * @param string|null $name            Parameter name to retrieve, or null to get the whole container.
     * @param mixed|null $default         Default value to use when the parameter is missing.
     * @return ParametersInterface|mixed
     */
    public function getEnv(?string $name = null, mixed $default = null): mixed
    {
        if ($this->envParams === null) {
            $this->envParams = new Parameters();
        }

        if ($name === null) {
            return $this->envParams;
        }

        return $this->envParams->get(name: $name, default: $default);
    }

    /**
     * Convert PHP superglobal $_FILES into more sane parameter=value structure
     * This handles form file input with brackets (name=files[])
     *
     * @return array
     */
    protected function mapPhpFiles(): array
    {
        $files = [];
        foreach ($_FILES as $fileName => $fileParams) {
            $files[$fileName] = [];
            foreach ($fileParams as $param => $data) {
                if (! is_array(value: $data)) {
                    $files[$fileName][$param] = $data;
                } else {
                    foreach ($data as $i => $v) {
                        $this->mapPhpFileParam(array: $files[$fileName], paramName: $param, index: $i, value: $v);
                    }
                }
            }
        }

        return $files;
    }

    /**
     * @param array $array
     * @param string $paramName
     * @param int|string $index
     * @param array|string $value
     */
    protected function mapPhpFileParam(array &$array, string $paramName, int|string $index, array|string $value): void
    {
        if (! is_array(value: $value)) {
            $array[$index][$paramName] = $value;
        } else {
            foreach ($value as $i => $v) {
                $this->mapPhpFileParam(array: $array[$index], paramName: $paramName, index: $i, value: $v);
            }
        }
    }

    /**
     * Detect the base URI for the request
     *
     * Looks at a variety of criteria in order to attempt to autodetect a base
     * URI, including rewrite URIs, proxy URIs, etc.
     *
     * @return string
     */
    protected function detectRequestUri(): string
    {
        $requestUri = null;
        $server     = $this->getServer();

        // IIS7 with URL Rewrite: make sure we get the unencoded url
        // (double slash problem).
        $iisUrlRewritten = $server->get(name: 'IIS_WasUrlRewritten');
        $unencodedUrl    = $server->get(name: 'UNENCODED_URL', default: '');
        if ('1' === $iisUrlRewritten && '' !== $unencodedUrl) {
            return $unencodedUrl;
        }

        $requestUri = $server->get(name: 'REQUEST_URI');

        // HTTP proxy requests setup request URI with scheme and host [and port]
        // + the URL path, only use URL path.
        if ($requestUri !== null) {
            return preg_replace(pattern: '#^[^/:]+://[^/]+#', replacement: '', subject: $requestUri);
        }

        // IIS 5.0, PHP as CGI.
        $origPathInfo = $server->get(name: 'ORIG_PATH_INFO');
        if ($origPathInfo !== null) {
            $queryString = $server->get(name: 'QUERY_STRING', default: '');
            if ($queryString !== '') {
                $origPathInfo .= '?' . $queryString;
            }
            return $origPathInfo;
        }

        return '/';
    }

    /**
     * Auto-detect the base path from the request environment
     *
     * Uses a variety of criteria in order to detect the base URL of the request
     * (i.e., anything additional to the document root).
     *
     * @return string
     */
    protected function detectBaseUrl(): string
    {
        $filename       = $this->getServer()->get(name: 'SCRIPT_FILENAME', default: '');
        $scriptName     = $this->getServer()->get(name: 'SCRIPT_NAME');
        $phpSelf        = $this->getServer()->get(name: 'PHP_SELF');
        $origScriptName = $this->getServer()->get(name: 'ORIG_SCRIPT_NAME');

        if ($scriptName !== null && basename(path: $scriptName) === $filename) {
            $baseUrl = $scriptName;
        } elseif ($phpSelf !== null && basename(path: $phpSelf) === $filename) {
            $baseUrl = $phpSelf;
        } elseif ($origScriptName !== null && basename(path: $origScriptName) === $filename) {
            // 1and1 shared hosting compatibility.
            $baseUrl = $origScriptName;
        } else {
            // Backtrack up the SCRIPT_FILENAME to find the portion
            // matching PHP_SELF.

            // Only for CLI requests argv[0] contains script filename
            // @see https://www.php.net/manual/en/reserved.variables.server.php
            if (PHP_SAPI === 'cli') {
                $argv = $this->getServer()->get(name: 'argv', default: []);
                if (isset($argv[0]) && is_string(value: $argv[0]) && $argv[0] !== '' && strpos(haystack: $filename, needle: $argv[0]) === 0) {
                    $filename = substr(string: $filename, offset: strlen(string: $argv[0]));
                }
            }

            $baseUrl  = '/';
            $basename = basename(path: $filename ?? '');
            if ($basename) {
                $path     = $phpSelf ? trim(string: $phpSelf, characters: '/') : '';
                $basePos  = strpos(haystack: $path, needle: $basename) ?: 0;
                $baseUrl .= substr(string: $path, offset: 0, length: $basePos) . $basename;
            }
        }

        // If the baseUrl is empty, then simply return it.
        if (empty($baseUrl)) {
            return '';
        }

        // Does the base URL have anything in common with the request URI?
        $requestUri = $this->getRequestUri();

        // Full base URL matches.
        if (0 === strpos(haystack: $requestUri, needle: $baseUrl)) {
            return $baseUrl;
        }

        // Directory portion of base path matches.
        $baseDir = str_replace(search: '\\', replace: '/', subject: dirname(path: $baseUrl));
        if (0 === strpos(haystack: $requestUri, needle: $baseDir)) {
            return $baseDir;
        }

        $truncatedRequestUri = $requestUri;

        if (false !== ($pos = strpos(haystack: $requestUri, needle: '?'))) {
            $truncatedRequestUri = substr(string: $requestUri, offset: 0, length: $pos);
        }

        $basename = basename(path: $baseUrl);

        // No match whatsoever
        if (empty($basename) || false === strpos(haystack: $truncatedRequestUri, needle: $basename)) {
            return '';
        }

        // If using mod_rewrite or ISAPI_Rewrite strip the script filename
        // out of the base path. $pos !== 0 makes sure it is not matching a
        // value from PATH_INFO or QUERY_STRING.
        if (
            strlen(string: $requestUri) >= strlen(string: $baseUrl)
            && (false !== ($pos = strpos(haystack: $requestUri, needle: $baseUrl)) && $pos !== 0)
        ) {
            $baseUrl = substr(string: $requestUri, offset: 0, length: $pos + strlen(string: $baseUrl));
        }

        return $baseUrl;
    }

    /**
     * Autodetect the base path of the request
     *
     * Uses several criteria to determine the base path of the request.
     *
     * @return string
     */
    protected function detectBasePath(): string
    {
        $baseUrl = $this->getBaseUrl();

        // Empty base url detected
        if ($baseUrl === '') {
            return '';
        }

        $filename = basename(path: $this->getServer()->get(name: 'SCRIPT_FILENAME', default: ''));

        // basename() matches the script filename; return the directory
        if (basename(path: $baseUrl) === $filename) {
            return str_replace(search: '\\', replace: '/', subject: dirname(path: $baseUrl));
        }

        // Base path is identical to base URL
        return $baseUrl;
    }
}
