<?php

namespace Jield\Http\Response;

use Jield\Http\Exception;
use Jield\Http\Header\ContentLength;
use Jield\Http\Response;
use Jield\Stdlib\ErrorHandler;

use function array_shift;
use function explode;
use function fgets;
use function file_exists;
use function get_resource_type;
use function implode;
use function is_resource;
use function is_string;
use function sprintf;
use function stream_get_contents;
use function strlen;
use function trim;
use function unlink;

use const E_WARNING;

/**
 * Represents an HTTP response message as PHP stream resource
 */
class Stream extends Response
{
    /**
     * The Content-Length value, if set
     *
     * @var int
     */
    protected int $contentLength;

    /**
     * The portion of the body that has already been streamed
     *
     * @var int
     */
    protected int $contentStreamed = 0;

    /**
     * Response as stream
     *
     * @var resource
     */
    protected $stream;

    /**
     * The name of the file containing the stream
     *
     * Will be empty if stream is not file-based.
     *
     * @var string
     */
    protected string $streamName;

    /**
     * Should we clean up the stream file when this response is closed?
     *
     * @var bool
     */
    protected bool $cleanup;

    /**
     * Set content length
     *
     * @param int|null $contentLength
     */
    public function setContentLength(?int $contentLength = null): void
    {
        $this->contentLength = $contentLength;
    }

    /**
     * Get content length
     *
     * @return int|null
     */
    public function getContentLength(): ?int
    {
        return $this->contentLength;
    }

    /**
     * Get the response as stream
     *
     * @return resource
     */
    public function getStream()
    {
        return $this->stream;
    }

    /**
     * Set the response stream
     *
     * @param resource $stream
     * @return $this
     */
    public function setStream($stream): static
    {
        $this->stream = $stream;
        return $this;
    }

    /**
     * Get the cleanup trigger
     *
     * @return bool
     */
    public function getCleanup(): bool
    {
        return $this->cleanup;
    }

    /**
     * Set the cleanup trigger
     *
     * @param bool $cleanup
     */
    public function setCleanup(bool $cleanup = true): void
    {
        $this->cleanup = $cleanup;
    }

    /**
     * Get file name associated with the stream
     *
     * @return string
     */
    public function getStreamName(): string
    {
        return $this->streamName;
    }

    /**
     * Set file name associated with the stream
     *
     * @param string $streamName Name to set
     * @return $this
     */
    public function setStreamName(string $streamName): static
    {
        $this->streamName = $streamName;
        return $this;
    }

    /**
     * Create a new Jield\Http\Response\Stream object from a stream
     *
     * @param string $responseString
     * @param  resource $stream
     * @return $this
     * @throws Exception\InvalidArgumentException
     * @throws Exception\OutOfRangeException
     */
    public static function fromStream(string $responseString, $stream): Stream|static
    {
        if (! is_resource(value: $stream) || get_resource_type(resource: $stream) !== 'stream') {
            throw new Exception\InvalidArgumentException(message: 'A valid stream is required');
        }

        $headerComplete = false;
        $headersString  = '';
        $responseArray  = [];

        if ($responseString) {
            $responseArray = explode(separator: "\n", string: $responseString);
        }

        while (! empty($responseArray)) {
            $nextLine        = array_shift(array: $responseArray);
            $headersString  .= $nextLine . "\n";
            $nextLineTrimmed = trim(string: $nextLine);
            if ($nextLineTrimmed === '') {
                $headerComplete = true;
                break;
            }
        }

        if (! $headerComplete) {
            while (false !== ($nextLine = fgets(stream: $stream))) {
                $headersString .= trim(string: $nextLine) . "\r\n";
                if ($nextLine === "\r\n" || $nextLine === "\n") {
                    $headerComplete = true;
                    break;
                }
            }
        }

        if (! $headerComplete) {
            throw new Exception\OutOfRangeException(message: 'End of header not found');
        }

        /** @var Stream $response */
        $response = static::fromString(string: $headersString);

        if (is_resource(value: $stream)) {
            $response->setStream(stream: $stream);
        }

        if (! empty($responseArray)) {
            $response->content = implode(separator: "\n", array: $responseArray);
        }

        $headers = $response->getHeaders();
        foreach ($headers as $header) {
            if ($header instanceof ContentLength) {
                $response->setContentLength(contentLength: (int) $header->getFieldValue());
                $contentLength = $response->getContentLength();
                if (strlen(string: $response->content) > $contentLength) {
                    throw new Exception\OutOfRangeException(message: sprintf(
                        'Too much content was extracted from the stream (%d instead of %d bytes)',
                        strlen(string: $response->content),
                        $contentLength
                    ));
                }
                break;
            }
        }

        return $response;
    }

    /**
     * Get the response body as string
     *
     * This method returns the body of the HTTP response (the content), as it
     * should be in it's readable version - that is, after decoding it (if it
     * was decoded), deflating it (if it was gzip compressed), etc.
     *
     * If you want to get the raw body (as transferred on wire) use
     * $this->getRawBody() instead.
     *
     * @return string
     */
    public function getBody(): string
    {
        if ($this->stream !== null) {
            $this->readStream();
        }
        return parent::getBody();
    }

    /**
     * Get the raw response body (as transferred "on wire") as string
     *
     * If the body is encoded (with Transfer-Encoding, not content-encoding -
     * IE "chunked" body), gzip compressed, etc. it will not be decoded.
     *
     * @return string
     */
    public function getRawBody(): string
    {
        if ($this->stream) {
            $this->readStream();
        }
        return $this->content;
    }

    /**
     * Read stream content and return it as string
     *
     * Function reads the remainder of the body from the stream and closes the stream.
     *
     * @return string
     */
    protected function readStream(): string
    {
        $contentLength = $this->getContentLength();
        if (null !== $contentLength) {
            $bytes = $contentLength - $this->contentStreamed;
        } else {
            $bytes = -1; // Read the whole buffer
        }

        if (! is_resource(value: $this->stream) || $bytes === 0) {
            return '';
        }

        $this->content         .= stream_get_contents(stream: $this->stream, length: $bytes);
        $this->contentStreamed += strlen(string: $this->content);

        if ($this->getContentLength() === $this->contentStreamed) {
            $this->stream = null;
        }
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        if (is_resource(value: $this->stream)) {
            $this->stream = null; //Could be listened by others
        }
        if ($this->cleanup && is_string(value: $this->streamName) && file_exists(filename: $this->streamName)) {
            ErrorHandler::start(errorLevel: E_WARNING);
            unlink(filename: $this->streamName);
            ErrorHandler::stop();
        }
    }
}
