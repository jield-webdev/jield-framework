<?php

declare(strict_types=1);

namespace Jield\ApiTools\ApiProblem\View;

use Jield\ApiTools\ApiProblem\ApiProblem;
use Laminas\View\Model\ViewModel;

class ApiProblemModel extends ViewModel
{
    /** @var string */
    protected string $captureTo = 'errors';

    /** @var ApiProblem */
    protected ApiProblem $problem;

    /** @var bool */
    protected bool $terminate = true;

    public function __construct(?ApiProblem $problem = null)
    {
        if ($problem instanceof ApiProblem) {
            $this->setApiProblem(problem: $problem);
        }
    }

    public function setApiProblem(ApiProblem $problem): static
    {
        $this->problem = $problem;

        return $this;
    }

    /**
     * @return ApiProblem
     */
    public function getApiProblem(): ApiProblem
    {
        return $this->problem;
    }
}
