<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Authentication;

use Jield\ApiTools\MvcAuth\Identity;
use Jield\ApiTools\MvcAuth\Identity\AuthenticatedIdentity;
use Jield\ApiTools\MvcAuth\MvcAuthEvent;
use Laminas\Http\Request as HttpRequest;
use Laminas\Http\Response;
use Laminas\Http\Response as HttpResponse;
use Laminas\Router\RouteMatch;
use function array_merge;
use function array_unique;
use function count;
use function rtrim;
use function strlen;

class DefaultAuthenticationListener
{
    /**
     * Attached authentication adapters
     *
     * @var AdapterInterface[]
     */
    private array $adapters = [];

    /**
     * Supported authentication types
     */
    private array $authenticationTypes = [];

    /**
     * Map of API/version to authentication type pairs
     */
    private array $authMap = [];

    /**
     * Attach an authentication adapter
     *
     * Adds the authentication adapter, and updates the list of supported
     * authentication types based on what the adapter provides.
     *
     */
    public function attach(AdapterInterface $adapter): void
    {
        $this->adapters[]          = $adapter;
        $this->authenticationTypes = array_unique(array: array_merge($this->authenticationTypes, $adapter->provides()));
    }

    /**
     * Add custom authentication types.
     *
     * This method allows specifiying additional authentication types, outside
     * of adapters, that your application supports. The values provided are
     * merged with any types already discovered.
     */
    public function addAuthenticationTypes(array $types): void
    {
        $this->authenticationTypes = array_unique(
            array: array_merge(
                $this->authenticationTypes,
                $types
            )
        );
    }

    /**
     * Set the API/version to authentication type map.
     *
     * @param array $map
     * @return void
     */
    public function setAuthMap(array $map): void
    {
        $this->authMap = $map;
    }

    public function __invoke(MvcAuthEvent $mvcAuthEvent): mixed
    {
        $mvcEvent = $mvcAuthEvent->getMvcEvent();
        $request  = $mvcEvent->getRequest();
        /** @var Response $response */
        $response = $mvcEvent->getResponse();

        if (
            !$request instanceof HttpRequest
            || $request->isOptions()
        ) {
            return null;
        }

        $type = $this->getTypeFromMap(routeMatch: $mvcEvent->getRouteMatch());
        if (false === $type && count(value: $this->adapters) > 1) {
            // Ambiguous situation; no matching type in map, but multiple
            // authentication adapters; return a guest identity.
            $identity = new Identity\GuestIdentity();
            $mvcEvent->setParam(name: AuthenticatedIdentity::class, value: $identity);
            return $identity;
        }

        $type = $type ?: $this->getTypeFromRequest(request: $request);
        if (false === $type) {
            // No authentication type known; trigger any pre-flight actions,
            // and return a guest identity.
            $this->triggerAdapterPreAuth(request: $request, response: $response);
            $identity = new Identity\GuestIdentity();
            $mvcEvent->setParam(name: AuthenticatedIdentity::class, value: $identity);
            return $identity;
        }

        // Authenticate against first matching adapter
        $identity = $this->authenticate(type: $type, request: $request, response: $response, mvcAuthEvent: $mvcAuthEvent);

        // If the adapter returns a response instance, return it directly.
        if ($identity instanceof HttpResponse) {
            return $identity;
        }

        // If no identity returned, create a guest identity
        if (!$identity instanceof Identity\IdentityInterface) {
            $identity = new Identity\GuestIdentity();
        }

        $mvcEvent->setParam(name: AuthenticatedIdentity::class, value: $identity);
        return $identity;
    }

    /**
     * Match the controller to an authentication type, based on the API to
     * which the controller belongs.
     */
    private function getTypeFromMap(?RouteMatch $routeMatch = null): false|string
    {
        if (!$routeMatch instanceof \Laminas\Router\RouteMatch) {
            return false;
        }

        $controller = $routeMatch->getParam(name: 'controller', default: false);
        if (false === $controller) {
            return false;
        }

        foreach ($this->authMap as $api => $type) {
            $api = rtrim(string: $api, characters: '\\') . '\\';
            if (strlen(string: $api) > strlen(string: (string)$controller)) {
                continue;
            }

            if (str_starts_with(haystack: (string)$controller, needle: $api)) {
                return $type;
            }
        }

        return false;
    }

    /**
     * Determine the authentication type based on request information
     *
     */
    private function getTypeFromRequest(HttpRequest $request): false|string
    {
        foreach ($this->adapters as $adapter) {
            $type = $adapter->getTypeFromRequest(request: $request);
            if (false !== $type) {
                return $type;
            }
        }

        return false;
    }

    /**
     * Trigger the preAuth routine of each adapter
     *
     * This method is triggered if no authentication type was discovered in the
     * request.
     */
    private function triggerAdapterPreAuth(HttpRequest $request, HttpResponse $response): void
    {
        foreach ($this->adapters as $adapter) {
            $adapter->preAuth(request: $request, response: $response);
        }
    }

    /**
     * Invoke the adapter matching the given $type in order to peform authentication
     */
    private function authenticate(string $type, HttpRequest $request, HttpResponse $response, MvcAuthEvent $mvcAuthEvent): false|\Laminas\Http\PhpEnvironment\Response|Identity\IdentityInterface
    {
        foreach ($this->adapters as $adapter) {
            if (!$adapter->matches(type: $type)) {
                continue;
            }

            return $adapter->authenticate(request: $request, response: $response, mvcAuthEvent: $mvcAuthEvent);
        }

        return false;
    }
}
