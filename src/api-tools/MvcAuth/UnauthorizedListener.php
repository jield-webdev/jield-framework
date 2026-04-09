<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth;

use Jield\ApiTools\ApiProblem\ApiProblem;
use Jield\ApiTools\ApiProblem\ApiProblemResponse;

class UnauthorizedListener
{
    /**
     * Determine if we have an authorization failure, and, if so, return a 403 response
     *
     */
    public function __invoke(MvcAuthEvent $mvcAuthEvent): ?ApiProblemResponse
    {
        if ($mvcAuthEvent->isAuthorized()) {
            return null;
        }

        $response = new ApiProblemResponse(apiProblem: new ApiProblem(status: 403, detail: 'Forbidden'));
        $mvcEvent = $mvcAuthEvent->getMvcEvent();
        $mvcEvent->setResponse(response: $response);

        return $response;
    }
}
