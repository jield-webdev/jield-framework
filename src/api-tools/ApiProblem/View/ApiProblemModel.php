<?php

declare(strict_types=1);

namespace Jield\ApiTools\ApiProblem\View;

use Jield\ApiTools\ApiProblem\ApiProblem;
use Jield\Mvc\View\Model\ViewModel;

class ApiProblemModel extends ViewModel
{
    protected ApiProblem $problem;

    public function __construct(?ApiProblem $problem = null)
    {
        parent::__construct();
        $this->setCaptureTo('errors');
        $this->setTerminal(true);

        if ($problem instanceof ApiProblem) {
            $this->setApiProblem(problem: $problem);
        }
    }

    public function setApiProblem(ApiProblem $problem): static
    {
        $this->problem = $problem;

        return $this;
    }

    public function getApiProblem(): ApiProblem
    {
        return $this->problem;
    }
}
