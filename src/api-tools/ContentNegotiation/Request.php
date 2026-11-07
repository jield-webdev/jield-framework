<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation;

use Laminas\Http\PhpEnvironment\Request as BaseRequest;
use RuntimeException;
use Webmozart\Assert\Assert;

use function fopen;
use function fwrite;
use function is_resource;
use function rewind;

/**
 * Custom request object
 *
 * Adds the ability to retrieve the request content as a stream, in order to
 * reduce memory usage.
 */
class Request extends BaseRequest
{
    /**
     * Stream URI or stream resource for content
     *
     * @var string|resource
     */
    protected mixed $contentStream = 'php://input';

    /**
     * Returns a stream URI for the content, allowing the user to use standard
     * filesystem functions in order to parse the incoming content.
     *
     * This is particularly useful for PUT and PATCH requests that contain file
     * uploads, as you can pipe the content piecemeal to the final destination,
     * preventing situations of memory exhaustion.
     *
     * @return resource Stream
     */
    public function getContentAsStream()
    {
        if (is_resource(value: $this->contentStream)) {
            rewind(stream: $this->contentStream);
            return $this->contentStream;
        }

        if (empty($this->content)) {
            $stream = fopen(filename: Assert::string($this->contentStream), mode: 'r');
            if ($stream === false) {
                throw new RuntimeException('Unable to open the request content stream.');
            }
            return $stream;
        }

        $stream = fopen(filename: 'php://temp', mode: 'r+');
        if ($stream === false) {
            throw new RuntimeException('Unable to buffer the request content.');
        }
        $this->contentStream = $stream;
        fwrite(stream: $this->contentStream, data: (string) $this->content);
        rewind(stream: $this->contentStream);
        return $this->contentStream;
    }
}
