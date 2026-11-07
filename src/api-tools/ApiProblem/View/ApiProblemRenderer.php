<?php

declare(strict_types=1);

namespace Jield\ApiTools\ApiProblem\View;

use Jield\Mvc\View\Renderer\JsonRenderer;
use Override;

class ApiProblemRenderer extends JsonRenderer
{
    /**
     * Whether or not to render exception stack traces in API-Problem payloads.
     *
     * @var bool
     */
    protected bool $displayExceptions = false;

    /**
     * Set display_exceptions flag.
     *
     * @param bool $flag
     * @return static
     */
    public function setDisplayExceptions(bool $flag): static
    {
        $this->displayExceptions = $flag;

        return $this;
    }

    /**
     * @param mixed $templateNameOrModel
     * @param iterable<non-empty-string, mixed>|null $variables
     * @return string
     */
    #[Override]
    public function render($templateNameOrModel, $variables = null): string
    {
        if (!$templateNameOrModel instanceof ApiProblemModel) {
            return '';
        }

        $apiProblem = $templateNameOrModel->getApiProblem();

        if ($this->displayExceptions) {
            $apiProblem->setDetailIncludesStackTrace(flag: true);
        }

        return parent::render(templateNameOrModel: $apiProblem->toArray());
    }
}
