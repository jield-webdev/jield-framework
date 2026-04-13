<?php

declare(strict_types=1);

namespace Jield\ApiTools\ApiProblem\View;

use Jield\ApiTools\ApiProblem\ApiProblem;

/**
 * Helper to render ApiProblemModel responses.
 */
class ApiProblemStrategy
{
    private ApiProblemRenderer $renderer;

    public function __construct(ApiProblemRenderer $renderer)
    {
        $this->renderer = $renderer;
    }

    public function render(ApiProblemModel $model): string
    {
        return $this->renderer->render(nameOrModel: $model);
    }

    /**
     * Retrieve the HTTP status from an ApiProblem object.
     *
     * Ensures that the status falls within the acceptable range (100 - 599).
     *
     */
    public function getStatusCodeFromApiProblem(ApiProblem $problem): int
    {
        $status = $problem->getStatus();

        if ($status < 100 || $status >= 600) {
            return 500;
        }

        return $status;
    }
}
