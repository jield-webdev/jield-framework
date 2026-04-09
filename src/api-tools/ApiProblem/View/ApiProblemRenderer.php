<?php

declare(strict_types=1);

namespace Jield\ApiTools\ApiProblem\View;

use Laminas\View\Model\ModelInterface;
use Laminas\View\Renderer\JsonRenderer;
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
     * @param array|null $values
     * @return string
     */
    #[Override]
    public function render(string|ModelInterface $nameOrModel, ?array $values = null): string
    {
        if (! $nameOrModel instanceof ApiProblemModel) {
            return '';
        }

        $apiProblem = $nameOrModel->getApiProblem();

        if ($this->displayExceptions) {
            $apiProblem->setDetailIncludesStackTrace(flag: true);
        }

        return parent::render(nameOrModel: $apiProblem->toArray());
    }
}
