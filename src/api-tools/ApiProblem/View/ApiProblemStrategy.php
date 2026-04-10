<?php

declare(strict_types=1);

namespace Jield\ApiTools\ApiProblem\View;

use Jield\ApiTools\ApiProblem\ApiProblem;
use Laminas\Http\Response as HttpResponse;
use Laminas\View\Strategy\JsonStrategy;
use Laminas\View\ViewEvent;

use Override;
use function is_string;

/**
 * Extension of the JSON strategy to handle the ApiProblemModel and provide
 * a Content-Type header appropriate to the response it describes.
 *
 * This will give the following content types:
 *
 * - application/problem+json for a result that contains a Problem
 *   API-formatted response
 */
class ApiProblemStrategy extends JsonStrategy
{
    public function __construct(ApiProblemRenderer $renderer)
    {
        $this->renderer = $renderer;
    }

    /**
     * Detect if we should use the ApiProblemRenderer based on model type.
     *
     */
    #[Override]
    public function selectRenderer(ViewEvent $e): ?ApiProblemRenderer
    {
        $model = $e->getModel();

        if (! $model instanceof ApiProblemModel) {
            // unrecognized model; do nothing
            return null;
        }

        // ApiProblemModel found
        return $this->renderer;
    }

    /**
     * Inject the response.
     *
     * Injects the response with the rendered content, and sets the content
     * type based on the detection that occurred during renderer selection.
     */
    #[Override]
    public function injectResponse(ViewEvent $e): void
    {
        $result = $e->getResult();
        if (! is_string(value: $result)) {
            // We don't have a string, and thus, no JSON
            return;
        }

        $model = $e->getModel();
        if (! $model instanceof ApiProblemModel) {
            // Model is not an ApiProblemModel; we cannot handle it here
            return;
        }

        $problem     = $model->getApiProblem();
        $statusCode  = $this->getStatusCodeFromApiProblem(problem: $problem);
        $contentType = ApiProblem::CONTENT_TYPE;

        // Populate response
        $response = $e->getResponse();
        if (! $response instanceof HttpResponse) {
            return;
        }

        $response->setStatusCode(code: $statusCode);
        $response->setContent(value: $result);

        $headers = $response->getHeaders();
        $headers->addHeaderLine(headerFieldNameOrLine: 'Content-Type', fieldValue: $contentType);
    }

    /**
     * Retrieve the HTTP status from an ApiProblem object.
     *
     * Ensures that the status falls within the acceptable range (100 - 599).
     *
     */
    protected function getStatusCodeFromApiProblem(ApiProblem $problem): int
    {
        $status = $problem->getStatus();

        if ($status < 100 || $status >= 600) {
            return 500;
        }

        return $status;
    }
}
