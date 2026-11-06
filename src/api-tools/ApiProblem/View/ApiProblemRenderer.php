<?php

declare(strict_types=1);

namespace Jield\ApiTools\ApiProblem\View;

use Jield\Mvc\View\Renderer\JsonRenderer;
use Laminas\View\Model\ModelInterface;
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
     * @param string|ModelInterface $nameOrModel
     * @param iterable<non-empty-string, mixed>|null $values
     * @return string
     */
    #[Override]
    public function render($nameOrModel, $values = null): string
    {
        if (!$nameOrModel instanceof ApiProblemModel) {
            return '';
        }

        $apiProblem = $nameOrModel->getApiProblem();

        if ($this->displayExceptions) {
            $apiProblem->setDetailIncludesStackTrace(flag: true);
        }

        return parent::render(templateNameOrModel: $apiProblem->toArray());
    }
}
