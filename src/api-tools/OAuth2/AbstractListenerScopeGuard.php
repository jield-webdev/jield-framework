<?php

declare(strict_types=1);

namespace Jield\ApiTools\OAuth2;

use Jield\ApiTools\Listener\AbstractRoutedListener;
use Jield\ApiTools\MvcAuth\Identity\AuthenticatedIdentity;
use Jield\Mvc\MvcEvent;
use Jield\Stdlib\Value;
use Laminas\Http\Request;
use Laminas\Http\Response;

/** Attach after authentication and HTTP method overrides, before application authorization. */
abstract class AbstractListenerScopeGuard
{
    final public function __invoke(MvcEvent $event): ?Response
    {
        $request = $event->getRequest();
        if (!$request instanceof Request || $request->isOptions()) {
            return null;
        }

        /** @var mixed $controller */
        $controller = $event->getRouteMatch()?->getParam('controller');
        $listener = is_string($controller) && is_subclass_of($controller, AbstractRoutedListener::class)
            ? $controller
            : null;
        /** @var mixed $identity */
        $identity = $event->getParam(AuthenticatedIdentity::class);

        // Existing session/guest authorization remains the application's responsibility.
        if (!$identity instanceof AuthenticatedIdentity && ($listener === null || !$listener::requiresAccessToken())) {
            return null;
        }
        /** @var mixed $rawIdentity */
        $rawIdentity = $identity instanceof AuthenticatedIdentity ? $identity->getAuthenticationIdentity() : [];
        $data = is_array($rawIdentity) ? $rawIdentity : [];
        $expires = filter_var($data['expires'] ?? 0, FILTER_VALIDATE_INT);
        if (!$identity instanceof AuthenticatedIdentity || ($expires === false || $expires <= time())) {
            return $this->deny($event, 401, 'invalid_token', 'An unexpired access token is required');
        }

        $required = $listener === null ? [] : $listener::getScopesForMethod($request->getMethod());
        $scopes = Scope::split(Value::nullableString($data['scope'] ?? null));
        if ($required === [] || array_intersect($scopes, $required) === []) {
            return $this->deny(
                $event,
                403,
                'insufficient_scope',
                'This token cannot access the requested endpoint',
                $required,
            );
        }
        if (
            $listener !== null
            && $this->isApplicationIdentity($data)
            && array_intersect($scopes, $required, Value::array($listener::getApplicationScopes())) === []
        ) {
            return $this->deny(
                $event,
                403,
                'insufficient_scope',
                'This endpoint does not permit application tokens',
                $required,
            );
        }

        return $this->validateIdentity($event, $data);
    }

    /** Apply application-specific user, tenant and revocation checks after scope matching. */
    abstract protected function validateIdentity(MvcEvent $event, array $identity): ?Response;

    protected function isApplicationIdentity(array $identity): bool
    {
        return ($identity['principal_type'] ?? null) === 'application';
    }

    /** @param list<string> $scopes Accepted alternatives, not a requirement to grant every scope. */
    protected function deny(
        MvcEvent $event,
        int $status,
        string $error,
        string $description,
        array $scopes = [],
    ): Response {
        /** @var Response $response */
        $response = $event->getResponse();
        $response->setStatusCode($status);
        $response->getHeaders()->addHeaderLine('Content-Type', 'application/json');
        $response->getHeaders()->addHeaderLine('Cache-Control', 'no-store');
        $response->getHeaders()->addHeaderLine(
            'WWW-Authenticate',
            'Bearer error="' . $error . '"' . ($scopes === [] ? '' : ', scope="' . implode(' ', $scopes) . '"'),
        );
        return Value::instance(
            $response->setContent(json_encode([
                'error' => $error,
                'error_description' => $description,
            ], JSON_THROW_ON_ERROR)),
            \Laminas\Http\Response::class,
        );
    }
}
