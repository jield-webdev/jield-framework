<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Authorization;

use Jield\ApiTools\MvcAuth\MvcAuthEvent;
use Jield\Stdlib\Value;
use Laminas\Http\Response as HttpResponse;

class DefaultAuthorizationPostListener
{
    /**
     * Determine if we have an authorization failure, and, if so, return a 403 response
     *
     */
    public function __invoke(MvcAuthEvent $mvcAuthEvent): ?HttpResponse
    {
        $mvcEvent = $mvcAuthEvent->getMvcEvent();
        $response = $mvcEvent->getResponse();

        if ($mvcAuthEvent->isAuthorized()) {
            if ($response instanceof HttpResponse && $response->getStatusCode() !== 200) {
                $response->setStatusCode(code: 200);
            }

            return null;
        }

        if (!$response instanceof HttpResponse) {
            return Value::nullableInstance($response, \Laminas\Http\Response::class);
        }

        $response->setStatusCode(code: 403);
        $response->setReasonPhrase(reasonPhrase: 'Forbidden');
        return $response;
    }
}
