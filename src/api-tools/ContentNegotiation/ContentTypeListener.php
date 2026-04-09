<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation;

use Jield\ApiTools\ApiProblem\ApiProblem;
use Jield\ApiTools\ApiProblem\ApiProblemResponse;
use Jield\Http\Header\ContentType;
use Jield\Http\PhpEnvironment\Request;
use Jield\Mvc\MvcEvent;
use function array_key_exists;
use function basename;
use function dirname;
use function file_exists;
use function in_array;
use function is_array;
use function is_object;
use function json_decode;
use function json_last_error;
use function method_exists;
use function parse_str;
use function preg_match;
use function sprintf;
use function strlen;
use function trim;
use function unlink;
use const JSON_ERROR_CTRL_CHAR;
use const JSON_ERROR_DEPTH;
use const JSON_ERROR_NONE;
use const JSON_ERROR_STATE_MISMATCH;
use const JSON_ERROR_SYNTAX;
use const JSON_ERROR_UTF8;

class ContentTypeListener
{
    /** @var array */
    protected array $jsonErrors
        = [
            JSON_ERROR_DEPTH          => 'Maximum stack depth exceeded',
            JSON_ERROR_STATE_MISMATCH => 'Underflow or the modes mismatch',
            JSON_ERROR_CTRL_CHAR      => 'Unexpected control character found',
            JSON_ERROR_SYNTAX         => 'Syntax error, malformed JSON',
            JSON_ERROR_UTF8           => 'Malformed UTF-8 characters, possibly incorrectly encoded',
        ];

    /**
     * Directory where upload files were written, if any
     *
     * @var string
     */
    protected string $uploadTmpDir;

    /**
     * Perform content negotiation
     *
     * For HTTP methods expecting body content, attempts to match the incoming
     * content-type against the list of allowed content types, and then performs
     * appropriate content deserialization.
     *
     * If an error occurs during deserialization, an ApiProblemResponse is
     * returned, indicating an issue with the submission.
     *
     */
    public function __invoke(MvcEvent $e): ?ApiProblemResponse
    {
        $request = $e->getRequest();
        if (!$request instanceof Request) {
            // Not an HTTP request; nothing to do
            return null;
        }

        $routeMatch    = $e->getRouteMatch();
        $parameterData = new ParameterDataContainer();

        // route parameters:
        $routeParams = $routeMatch->getParams();
        $parameterData->setRouteParams(routeParams: $routeParams);

        // query parameters:
        $parameterData->setQueryParams(queryParams: $request->getQuery()->toArray());

        // body parameters:
        $bodyParams = [];
        $contentType = $request->getHeader(name: 'Content-Type');
        /** @var null|ContentType $contentType */
        switch ($request->getMethod()) {
            case $request::METHOD_POST:
                if ($contentType && $contentType->match(matchAgainst: 'application/json')) {
                    $bodyParams = $this->decodeJson(json: $request->getContent());
                    break;
                }

                $bodyParams = $request->getPost()->toArray();
                break;
            case $request::METHOD_PATCH:
            case $request::METHOD_PUT:
            case $request::METHOD_DELETE:
                $content = $request->getContent();

                if ($contentType && $contentType->match(matchAgainst: 'multipart/form-data')) {
                    try {
                        $parser     = new MultipartContentParser(contentType: $contentType, request: $request);
                        $bodyParams = $parser->parse();
                    } catch (Exception\ExceptionInterface $e) {
                        $bodyParams = new ApiProblemResponse(apiProblem: new ApiProblem(
                            status: 400,
                            detail: $e
                        ));
                        break;
                    }

                    if ($request->getFiles()->count()) {
                        $this->attachFileCleanupListener(event: $e, uploadTmpDir: $parser->getUploadTempDir());
                    }

                    break;
                }

                if ($contentType && $contentType->match(matchAgainst: 'application/json')) {
                    $bodyParams = $this->decodeJson(json: $content);
                    break;
                }

                // Try to assume JSON if content starts like JSON and no explicit Content-Type was provided
                if (!$bodyParams && strlen(string: $content) > 0 && in_array(needle: $content[0], haystack: ['{', '['], strict: true)) {
                    $bodyParams = $this->decodeJson(json: $content);
                }

                if (!$bodyParams || $bodyParams instanceof ApiProblemResponse) {
                    parse_str(string: $content, result: $bodyParams);
                }

                break;
            default:
                break;
        }

        if ($bodyParams instanceof ApiProblemResponse) {
            return $bodyParams;
        }

        $parameterData->setBodyParams(bodyParams: $bodyParams);
        $e->setParam(name: 'JieldContentNegotiationParameterData', value: $parameterData);
        return null;
    }

    /**
     * Remove upload files if still present in filesystem
     *
     */
    public function onFinish(MvcEvent $e): void
    {
        $request = $e->getRequest();
        if (!$request instanceof Request) {
            return;
        }

        foreach ($request->getFiles() as $fileInfo) {
            if (dirname(path: (string)$fileInfo['tmp_name']) !== $this->uploadTmpDir) {
                // File was moved
                continue;
            }

            if (!preg_match(pattern: '/^jieldc/', subject: basename(path: (string)$fileInfo['tmp_name']))) {
                // File was moved
                continue;
            }

            if (!file_exists(filename: $fileInfo['tmp_name'])) {
                continue;
            }

            unlink(filename: $fileInfo['tmp_name']);
        }
    }

    /**
     * Attempt to decode a JSON string
     *
     * Decodes a JSON string and returns it; if invalid, returns
     * an ApiProblemResponse.
     *
     * @param string $json
     * @return mixed|ApiProblemResponse
     */
    public function decodeJson(string $json): mixed
    {
        // Trim whitespace from front and end of string to avoid parse errors
        $json = trim(string: $json);

        // If the data is empty, return an empty array to prevent JSON decode errors
        if ($json === '' || $json === '0') {
            return [];
        }

        $data    = json_decode(json: $json, associative: true);
        $isArray = is_array(value: $data);

        // Decode 'application/hal+json' to 'application/json' by merging _embedded into the array
        if ($isArray && isset($data['_embedded'])) {
            foreach ($data['_embedded'] as $key => $value) {
                $data[$key] = $value;
            }

            unset($data['_embedded']);
        }

        if ($isArray) {
            return $data;
        }

        $error = json_last_error();
        if ($error === JSON_ERROR_NONE) {
            return $data;
        }

        $message = array_key_exists(key: $error, array: $this->jsonErrors) ? $this->jsonErrors[$error] : 'Unknown error';

        return new ApiProblemResponse(
            apiProblem: new ApiProblem(status: 400, detail: sprintf('JSON decoding error: %s', $message))
        );
    }

    /**
     * Attach the file cleanup listener
     *
     * @param string $uploadTmpDir Directory in which file uploads were made
     */
    protected function attachFileCleanupListener(MvcEvent $event, string $uploadTmpDir): void
    {
        $target = $event->getTarget();
        if (!$target || !is_object(value: $target) || !method_exists(object_or_class: $target, method: 'getEventManager')) {
            return;
        }

        $this->uploadTmpDir = $uploadTmpDir;
        $events             = $target->getEventManager();
        $events->attach('finish', $this->onFinish(...), 1000);
    }
}
