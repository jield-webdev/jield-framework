<?php

declare(strict_types=1);

namespace Jield\ApiTools\ApiProblem\View;

use Laminas\View\Model\ModelInterface;
use function json_encode;
use const JSON_PARTIAL_OUTPUT_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

class ApiProblemRenderer
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
    public function render(string|ModelInterface $nameOrModel, ?array $values = null): string
    {
        if (! $nameOrModel instanceof ApiProblemModel) {
            return '';
        }

        $apiProblem = $nameOrModel->getApiProblem();

        if ($this->displayExceptions) {
            $apiProblem->setDetailIncludesStackTrace(flag: true);
        }

        return json_encode(
            value: $apiProblem->toArray(),
            flags: JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
        );
    }
}
