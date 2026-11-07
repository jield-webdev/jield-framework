<?php

declare(strict_types=1);

namespace Jield\ApiTools\ApiProblem;

use Jield\Stdlib\Value;
use Laminas\Http\Headers;
use Laminas\Http\Response;
use Override;

use function json_encode;

use const JSON_PARTIAL_OUTPUT_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * Represents an ApiProblem response payload.
 */
class ApiProblemResponse extends Response
{
    /**
     * Flags to use with json_encode.
     *
     * @var int
     */
    protected int $jsonFlags;

    public function __construct(
        protected ApiProblem $apiProblem,
    ) {
        $this->setCustomStatusCode(code: $this->apiProblem->getStatus());

        if ($this->apiProblem->getTitle() !== null) {
            $this->setReasonPhrase(reasonPhrase: Value::string($this->apiProblem->getTitle()));
        }

        $this->jsonFlags = JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR;
    }

    /**
     * @return ApiProblem
     */
    public function getApiProblem(): ApiProblem
    {
        return $this->apiProblem;
    }

    /**
     * Retrieve the content.
     *
     * Serializes the composed ApiProblem instance to JSON.
     *
     * @return string
     */
    #[Override]
    public function getContent(): string
    {
        return json_encode(value: $this->apiProblem->toArray(), flags: $this->jsonFlags) ?: '{}';
    }

    /**
     * Retrieve headers.
     *
     * Proxies to parent class, but then checks if we have an content-type
     * header; if not, sets it, with a value of "application/problem+json".
     *
     * @return Headers
     */
    #[Override]
    public function getHeaders(): Headers
    {
        $headers = parent::getHeaders();
        if (!$headers->has('content-type')) {
            $headers->addHeaderLine(headerFieldNameOrLine: 'content-type', fieldValue: ApiProblem::CONTENT_TYPE);
        }

        return $headers;
    }

    /**
     * Override reason phrase handling.
     *
     * If no corresponding reason phrase is available for the current status
     * code, return "Unknown Error".
     *
     * @return string
     */
    #[Override]
    public function getReasonPhrase(): string
    {
        if (!empty($this->reasonPhrase)) {
            return $this->reasonPhrase;
        }

        return Value::string($this->recommendedReasonPhrases[$this->statusCode] ?? 'Unknown Error');
    }
}
