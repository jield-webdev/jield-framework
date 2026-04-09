<?php

namespace Jield\Http;

use Jield\Stdlib\ErrorHandler;
use Jield\Stdlib\ResponseInterface;
use function array_shift;
use function count;
use function explode;
use function function_exists;
use function gzdecode;
use function gzinflate;
use function gzuncompress;
use function hexdec;
use function implode;
use function in_array;
use function is_array;
use function is_numeric;
use function ord;
use function preg_match;
use function sprintf;
use function strlen;
use function strtolower;
use function substr;
use function trim;
use function unpack;

/**
 * HTTP Response
 *
 * @link      http://www.w3.org/Protocols/rfc2616/rfc2616-sec6.html#sec6
 */
class Response extends AbstractMessage implements ResponseInterface
{
    /**#@+
     *
     * @const int Status codes
     */
    public const int STATUS_CODE_CUSTOM = 0;
    public const int STATUS_CODE_100 = 100;
    public const int STATUS_CODE_101 = 101;
    public const int STATUS_CODE_102 = 102;
    public const int STATUS_CODE_200 = 200;
    public const int STATUS_CODE_201 = 201;
    public const int STATUS_CODE_202 = 202;
    public const int STATUS_CODE_203 = 203;
    public const int STATUS_CODE_204 = 204;
    public const int STATUS_CODE_205 = 205;
    public const int STATUS_CODE_206 = 206;
    public const int STATUS_CODE_207 = 207;
    public const int STATUS_CODE_208 = 208;
    public const int STATUS_CODE_226 = 226;
    public const int STATUS_CODE_300 = 300;
    public const int STATUS_CODE_301 = 301;
    public const int STATUS_CODE_302 = 302;
    public const int STATUS_CODE_303 = 303;
    public const int STATUS_CODE_304 = 304;
    public const int STATUS_CODE_305 = 305;
    public const int STATUS_CODE_306 = 306;
    public const int STATUS_CODE_307 = 307;
    public const int STATUS_CODE_308 = 308;
    public const int STATUS_CODE_400 = 400;
    public const int STATUS_CODE_401 = 401;
    public const int STATUS_CODE_402 = 402;
    public const int STATUS_CODE_403 = 403;
    public const int STATUS_CODE_404 = 404;
    public const int STATUS_CODE_405 = 405;
    public const int STATUS_CODE_406 = 406;
    public const int STATUS_CODE_407 = 407;
    public const int STATUS_CODE_408 = 408;
    public const int STATUS_CODE_409 = 409;
    public const int STATUS_CODE_410 = 410;
    public const int STATUS_CODE_411 = 411;
    public const int STATUS_CODE_412 = 412;
    public const int STATUS_CODE_413 = 413;
    public const int STATUS_CODE_414 = 414;
    public const int STATUS_CODE_415 = 415;
    public const int STATUS_CODE_416 = 416;
    public const int STATUS_CODE_417 = 417;
    public const int STATUS_CODE_418 = 418;
    public const int STATUS_CODE_422 = 422;
    public const int STATUS_CODE_423 = 423;
    public const int STATUS_CODE_424 = 424;
    public const int STATUS_CODE_425 = 425;
    public const int STATUS_CODE_426 = 426;
    public const int STATUS_CODE_428 = 428;
    public const int STATUS_CODE_429 = 429;
    public const int STATUS_CODE_431 = 431;
    public const int STATUS_CODE_451 = 451;
    public const int STATUS_CODE_444 = 444;
    public const int STATUS_CODE_499 = 499;
    public const int STATUS_CODE_500 = 500;
    public const int STATUS_CODE_501 = 501;
    public const int STATUS_CODE_502 = 502;
    public const int STATUS_CODE_503 = 503;
    public const int STATUS_CODE_504 = 504;
    public const int STATUS_CODE_505 = 505;
    public const int STATUS_CODE_506 = 506;
    public const int STATUS_CODE_507 = 507;
    public const int STATUS_CODE_508 = 508;
    public const int STATUS_CODE_510 = 510;
    public const int STATUS_CODE_511 = 511;
    public const int STATUS_CODE_599 = 599;
    /**#@-*/

    /**
     * @internal
     */
    public const int MIN_STATUS_CODE_VALUE = 100;

    /**
     * @internal
     */
    public const int MAX_STATUS_CODE_VALUE = 599;

    /** @var array Recommended Reason Phrases */
    protected array $recommendedReasonPhrases
        = [
            // INFORMATIONAL CODES
            100 => 'Continue',
            101 => 'Switching Protocols',
            102 => 'Processing',
            // SUCCESS CODES
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            207 => 'Multi-status',
            208 => 'Already Reported',
            226 => 'IM Used',
            // REDIRECTION CODES
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            306 => 'Switch Proxy', // Deprecated
            307 => 'Temporary Redirect',
            308 => 'Permanent Redirect',
            // CLIENT ERROR
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Time-out',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Content Too Large',
            414 => 'URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Range Not Satisfiable',
            417 => 'Expectation Failed',
            418 => 'I\'m a teapot',
            422 => 'Unprocessable Content',
            423 => 'Locked',
            424 => 'Failed Dependency',
            425 => 'Too Early',
            426 => 'Upgrade Required',
            428 => 'Precondition Required',
            429 => 'Too Many Requests',
            431 => 'Request Header Fields Too Large',
            444 => 'Connection Closed Without Response',
            451 => 'Unavailable For Legal Reasons',
            499 => 'Client Closed Request',
            // SERVER ERROR
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version not supported',
            506 => 'Variant Also Negotiates',
            507 => 'Insufficient Storage',
            508 => 'Loop Detected',
            510 => 'Not Extended',
            511 => 'Network Authentication Required',
            599 => 'Network Connect Timeout Error',
        ];

    /** @var int Status code */
    protected int $statusCode = 200;

    /** @var string|null Null means it will be looked up from the $reasonPhrase list above */
    protected ?string $reasonPhrase;

    /**
     * Populate object from string
     *
     * @param string $string
     * @return static
     * @throws Exception\InvalidArgumentException
     */
    public static function fromString(string $string): static
    {
        $lines = explode(separator: "\r\n", string: $string);
        if (!is_array(value: $lines) || count(value: $lines) === 1) {
            $lines = explode(separator: "\n", string: $string);
        }

        $firstLine = array_shift(array: $lines);

        $response = new self();
        $response->parseStatusLine(line: $firstLine);

        /**
         * @link https://tools.ietf.org/html/rfc7231#section-6.2.1
         */
        if ($response->statusCode === static::STATUS_CODE_100) {
            $next = array_shift(array: $lines); // take next line
            $next = empty($next) ? array_shift(array: $lines) : $next; // take next or skip if empty
            $response->parseStatusLine(line: $next);
        }

        if (count(value: $lines) === 0) {
            return $response;
        }

        $isHeader = true;
        $headers  = $content = [];

        foreach ($lines as $line) {
            if ($isHeader && $line === '') {
                $isHeader = false;
                continue;
            }

            if ($isHeader) {
                if (preg_match(pattern: "/[\r\n]/", subject: $line)) {
                    throw new Exception\RuntimeException(message: 'CRLF injection detected');
                }
                $headers[] = $line;
                continue;
            }

            if (
                empty($content)
                && preg_match(pattern: '/^[a-z0-9!#$%&\'*+.^_`|~-]+:$/i', subject: $line)
            ) {
                throw new Exception\RuntimeException(message: 'CRLF injection detected');
            }

            $content[] = $line;
        }

        if ($headers) {
            $response->headers = implode(separator: "\r\n", array: $headers);
        }

        if ($content) {
            $response->setContent(value: implode(separator: "\r\n", array: $content));
        }

        return $response;
    }

    /**
     * @param string $line
     * @throws Exception\InvalidArgumentException
     * @throws \RuntimeException
     */
    protected function parseStatusLine(string $line): void
    {
        $regex   = '/^HTTP\/(?P<version>1\.[01]|2) (?P<status>\d{3})(?:[ ]+(?P<reason>.*))?$/';
        $matches = [];
        if (!preg_match(pattern: $regex, subject: $line, matches: $matches)) {
            throw new Exception\InvalidArgumentException(
                message: 'A valid response status line was not found in the provided string'
            );
        }

        $this->version = $matches['version'];
        $this->setStatusCode(code: $matches['status']);
        $this->setReasonPhrase(reasonPhrase: $matches['reason'] ?? '');
    }

    /**
     * @return Header\SetCookie[]
     */
    public function getCookie(): array
    {
        return $this->getHeaders()->get(name: 'Set-Cookie');
    }

    /**
     * Set HTTP status code and (optionally) message
     *
     * @param int $code
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function setStatusCode(int $code): static
    {
        if (
            !is_numeric(value: $code)
            || $code < static::MIN_STATUS_CODE_VALUE
            || $code > static::MAX_STATUS_CODE_VALUE
        ) {
            throw new Exception\InvalidArgumentException(message: sprintf(
                'Invalid status code "%s"; must be an integer between %d and %d, inclusive',
                $code,
                static::MIN_STATUS_CODE_VALUE,
                static::MAX_STATUS_CODE_VALUE
            ));
        }

        return $this->saveStatusCode(code: $code);
    }

    /**
     * Retrieve HTTP status code
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Set custom HTTP status code
     *
     * @param int $code
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function setCustomStatusCode(int $code): static
    {
        return $this->saveStatusCode(code: $code);
    }

    /**
     * Assign status code
     *
     * @param int $code
     * @return $this
     */
    protected function saveStatusCode(int $code): static
    {
        $this->reasonPhrase = null;
        $this->statusCode   = $code;
        return $this;
    }

    /**
     * @param string $reasonPhrase
     * @return $this
     */
    public function setReasonPhrase(string $reasonPhrase): static
    {
        $this->reasonPhrase = trim(string: $reasonPhrase);
        return $this;
    }

    /**
     * Get HTTP status message
     *
     * @return string
     */
    public function getReasonPhrase(): ?string
    {
        if (empty($this->reasonPhrase) && isset($this->recommendedReasonPhrases[$this->statusCode])) {
            $this->reasonPhrase = $this->recommendedReasonPhrases[$this->statusCode];
        }
        return $this->reasonPhrase;
    }

    /**
     * Get the body of the response
     *
     * @return string
     */
    public function getBody(): string
    {
        $body = (string)$this->getContent();

        $transferEncoding = $this->getHeaders()->get(name: 'Transfer-Encoding');

        if (!empty($transferEncoding) && strtolower(string: $transferEncoding->getFieldValue()) === 'chunked') {
            $body = $this->decodeChunkedBody(body: $body);
        }

        $contentEncoding = $this->getHeaders()->get(name: 'Content-Encoding');

        if (!empty($contentEncoding)) {
            $contentEncoding = $contentEncoding->getFieldValue();
            if ($contentEncoding === 'gzip') {
                $body = $this->decodeGzip(body: $body);
            } elseif ($contentEncoding === 'deflate') {
                $body = $this->decodeDeflate(body: $body);
            }
        }

        return $body;
    }

    /**
     * Does the status code indicate a client error?
     *
     * @return bool
     */
    public function isClientError(): bool
    {
        $code = $this->getStatusCode();
        return $code < 500 && $code >= 400;
    }

    /**
     * Is the request forbidden due to ACLs?
     *
     * @return bool
     */
    public function isForbidden(): bool
    {
        return 403 === $this->getStatusCode();
    }

    /**
     * Is the current status "informational"?
     *
     * @return bool
     */
    public function isInformational(): bool
    {
        $code = $this->getStatusCode();
        return $code >= 100 && $code < 200;
    }

    /**
     * Does the status code indicate the resource is not found?
     *
     * @return bool
     */
    public function isNotFound(): bool
    {
        return 404 === $this->getStatusCode();
    }

    /**
     * Does the status code indicate the resource is gone?
     *
     * @return bool
     */
    public function isGone(): bool
    {
        return 410 === $this->getStatusCode();
    }

    /**
     * Do we have a normal, OK response?
     *
     * @return bool
     */
    public function isOk(): bool
    {
        return 200 === $this->getStatusCode();
    }

    /**
     * Does the status code reflect a server error?
     *
     * @return bool
     */
    public function isServerError(): bool
    {
        $code = $this->getStatusCode();
        return 500 <= $code && 600 > $code;
    }

    /**
     * Do we have a redirect?
     *
     * @return bool
     */
    public function isRedirect(): bool
    {
        $code = $this->getStatusCode();
        return 300 <= $code && 400 > $code;
    }

    /**
     * Was the response successful?
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        $code = $this->getStatusCode();
        return 200 <= $code && 300 > $code;
    }

    /**
     * Render the status line header
     *
     * @return string
     */
    public function renderStatusLine(): string
    {
        $status = sprintf(
            'HTTP/%s %d %s',
            $this->getVersion(),
            $this->getStatusCode(),
            $this->getReasonPhrase()
        );
        return trim(string: $status);
    }

    /**
     * Render entire response as HTTP response string
     *
     * @return string
     */
    public function toString(): string
    {
        $str = $this->renderStatusLine() . "\r\n";
        $str .= $this->getHeaders()->toString();
        $str .= "\r\n";
        $str .= $this->getContent();
        return $str;
    }

    /**
     * Decode a "chunked" transfer-encoded body and return the decoded text
     *
     * @param string $body
     * @return string
     * @throws \RuntimeException
     */
    protected function decodeChunkedBody(string $body): string
    {
        $decBody = '';

        $offset = 0;

        while (true) {
            if (!preg_match(pattern: "/^([\da-fA-F]+)[^\r\n]*\r\n/sm", subject: $body, matches: $m, flags: 0, offset: $offset)) {
                if (trim(string: substr(string: $body, offset: $offset))) {
                    // Message was not consumed completely!
                    throw new Exception\RuntimeException(
                        message: 'Error parsing body - doesn\'t seem to be a chunked message'
                    );
                }
                // Message was consumed completely
                break;
            }

            $length  = hexdec(hex_string: trim(string: $m[1]));
            $cut     = strlen(string: $m[0]);
            $decBody .= substr(string: $body, offset: $offset + $cut, length: $length);
            $offset  += $cut + $length + 2;
        }

        return $decBody;
    }

    /**
     * Decode a gzip encoded message (when Content-encoding = gzip)
     *
     * Currently requires PHP with zlib support
     *
     * @param string $body
     * @return string
     * @throws \RuntimeException
     */
    protected function decodeGzip(string $body): string
    {
        if (!function_exists(function: 'gzdecode')) {
            throw new Exception\RuntimeException(
                message: 'zlib extension is required in order to decode "gzip" encoding'
            );
        }

        if (
            $body === ''
            || ($this->getHeaders()->has(name: 'content-length')
                && (int)$this->getHeaders()->get(name: 'content-length')->getFieldValue() === 0)
        ) {
            return '';
        }

        ErrorHandler::start();
        $return = gzdecode(data: $body);
        $test   = ErrorHandler::stop();
        if ($test) {
            throw new Exception\RuntimeException(
                message: 'Error occurred during gzip inflation',
                code: 0,
                previous: $test
            );
        }
        return $return;
    }

    /**
     * Decode a zlib deflated message (when Content-encoding = deflate)
     *
     * Currently requires PHP with zlib support
     *
     * @param string $body
     * @return string
     * @throws \RuntimeException
     */
    protected function decodeDeflate(string $body): string
    {
        if (!function_exists(function: 'gzuncompress')) {
            throw new Exception\RuntimeException(
                message: 'zlib extension is required in order to decode "deflate" encoding'
            );
        }

        if (
            $this->getHeaders()->has(name: 'content-length')
            && 0 === (int)$this->getHeaders()->get(name: 'content-length')->getFieldValue()
        ) {
            return '';
        }

        /**
         * Some servers (IIS ?) send a broken deflate response, without the
         * RFC-required zlib header.
         *
         * We try to detect the zlib header, and if it does not exist we
         * teat the body is plain DEFLATE content.
         *
         * This method was adapted from PEAR HTTP_Request2 by (c) Alexey Borzov
         *
         * @link https://getjield.org/issues/browse/Jield-6040
         */
        $zlibHeader = unpack(format: 'n', string: substr(string: $body, offset: 0, length: 2));

        if ($zlibHeader[1] % 31 === 0 && ord(character: $body[0]) === 0x78 && in_array(needle: ord(character: $body[1]), haystack: [0x01, 0x5e, 0x9c, 0xda])) {
            return gzuncompress(data: $body);
        }
        return gzinflate(data: $body);
    }
}
