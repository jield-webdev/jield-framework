<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation;

use Jield\ApiTools\ApiProblem\ApiProblem;
use Jield\ApiTools\ApiProblem\ApiProblemResponse;
use Jield\Http\Header\Accept as AcceptHeader;
use Jield\Http\Headers as HttpHeaders;
use Jield\Mvc\MvcEvent;

use Override;
use ArrayIterator;
use function is_array;
use function is_string;
use function method_exists;

class AcceptFilterListener extends ContentTypeFilterListener
{
    /**
     * Test if the accept content-type received is allowable.
     *
     */
    #[Override]
    public function onRoute(MvcEvent $e): ?ApiProblemResponse
    {
        if (empty($this->config)) {
            return null;
        }

        $controllerName = $e->getRouteMatch()->getParam(name: 'controller');
        if (! isset($this->config[$controllerName])) {
            return null;
        }

        $request = $e->getRequest();
        if (! method_exists(object_or_class: $request, method: 'getHeaders')) {
            // Not an HTTP request; nothing to do
            return null;
        }

        $headers = $request->getHeaders();

        $matched = false;
        if (is_string(value: $this->config[$controllerName])) {
            $matched = $this->validateMediaType(match: $this->config[$controllerName], headers: $headers);
        } elseif (is_array(value: $this->config[$controllerName])) {
            foreach ($this->config[$controllerName] as $whitelistType) {
                $matched = $this->validateMediaType(match: $whitelistType, headers: $headers);
                if ($matched) {
                    break;
                }
            }
        }

        if (! $matched) {
            return new ApiProblemResponse(
                apiProblem: new ApiProblem(status: 406, detail: 'Cannot honor Accept type specified')
            );
        }

        return null;
    }

    /**
     * Validate the passed mediatype against the appropriate header
     *
     * @param string $match
     */
    protected function validateMediaType(string $match, HttpHeaders $headers): bool
    {
        if (! $headers->has(name: 'accept')) {
            return true;
        }

        $accept = $headers->get(name: 'accept');
        if ($accept instanceof AcceptHeader) {
            return (bool) $accept->match(matchAgainst: $match);
        }

        if ($accept instanceof ArrayIterator) {
            foreach ($accept as $header) {
                if ($header instanceof AcceptHeader && $header->match(matchAgainst: $match)) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }
}
