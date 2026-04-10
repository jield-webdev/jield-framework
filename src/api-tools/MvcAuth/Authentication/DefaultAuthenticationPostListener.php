<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Authentication;

use Jield\ApiTools\MvcAuth\MvcAuthEvent;
use Laminas\Http\Response as HttpResponse;

class DefaultAuthenticationPostListener
{
    /**
     * Determine if we have an authentication failure, and, if so, return a 401 response
     *
     */
    public function __invoke(MvcAuthEvent $mvcAuthEvent): ?HttpResponse
    {
        if (! $mvcAuthEvent->hasAuthenticationResult()) {
            return null;
        }

        $authResult = $mvcAuthEvent->getAuthenticationResult();
        if ($authResult->isValid()) {
            return null;
        }

        $mvcEvent = $mvcAuthEvent->getMvcEvent();
        $response = $mvcEvent->getResponse();
        if (! $response instanceof HttpResponse) {
            return $response;
        }

        $response->setStatusCode(code: 401);
        $response->setReasonPhrase(reasonPhrase: 'Unauthorized');
        return $response;
    }
}
