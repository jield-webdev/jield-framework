<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation;

use ArrayIterator;
use Jield\ApiTools\ApiProblem\ApiProblem;
use Jield\ApiTools\ApiProblem\ApiProblemResponse;
use Jield\Mvc\MvcEvent;
use Laminas\Http\Header\Accept as AcceptHeader;
use Laminas\Http\Headers as HttpHeaders;
use Override;
use Webmozart\Assert\Assert;

use function is_array;
use function is_string;
use function method_exists;

class AcceptFilterListener extends ContentTypeFilterListener
{
    /**
     * Test if the accept content-type received is allowable.
     */
    #[Override]
    public function onRoute(MvcEvent $e): ?ApiProblemResponse
    {
        if ($this->config === []) {
            return null;
        }

        /** @mago-var mixed $controllerName */
        $controllerName = Assert::notNull($e->getRouteMatch())->getParam(name: 'controller');
        if (($this->config[$controllerName] ?? null) === null) {
            return null;
        }

        $request = $e->getRequest();
        if (!method_exists(object_or_class: $request, method: 'getHeaders')) {
            // Not an HTTP request; nothing to do
            return null;
        }

        /** @mago-var mixed $headers */
        $headers = $request->getHeaders();

        $matched = false;
        // These alternatives are mutually exclusive; the following code consumes the selected state.
        // @mago-expect lint:no-else-clause(1)
        if (is_string(value: $this->config[$controllerName])) {
            $matched = $this->validateMediaType(
                match: $this->config[$controllerName],
                headers: Assert::isInstanceOf($headers, \Laminas\Http\Headers::class),
            );
        } elseif (is_array(value: $this->config[$controllerName])) {
            /** @mago-var mixed $whitelistType */
            foreach ($this->config[$controllerName] as $whitelistType) {
                $matched = $this->validateMediaType(
                    match: Assert::string($whitelistType),
                    headers: Assert::isInstanceOf($headers, \Laminas\Http\Headers::class),
                );
                if ($matched) {
                    break;
                }
            }
        }

        if (!$matched) {
            return new ApiProblemResponse(apiProblem: new ApiProblem(
                status: 406,
                detail: 'Cannot honor Accept type specified',
            ));
        }

        return null;
    }

    /**
     * Validate the passed mediatype against the appropriate header
     */
    protected function validateMediaType(string $match, HttpHeaders $headers): bool
    {
        if (!$headers->has('accept')) {
            return true;
        }

        $accept = $headers->get('accept');
        if ($accept instanceof AcceptHeader) {
            return (bool) $accept->match($match);
        }

        if ($accept instanceof ArrayIterator) {
            /** @mago-var mixed $header */
            foreach ($accept as $header) {
                if ($header instanceof AcceptHeader && $header->match($match)) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }
}
