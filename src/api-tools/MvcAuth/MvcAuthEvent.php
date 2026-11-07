<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth;

use Jield\ApiTools\MvcAuth\Identity\IdentityInterface;
use Jield\Mvc\MvcEvent;
use Jield\Stdlib\Value;
use Laminas\Authentication\Result;
use Laminas\EventManager\Event;

/**
 * @extends \Laminas\EventManager\Event<object|string|null, array<string, mixed>>
 */
class MvcAuthEvent extends Event
{
    public const string EVENT_AUTHENTICATION = 'authentication';

    public const string EVENT_AUTHENTICATION_POST = 'authentication.post';

    public const string EVENT_AUTHORIZATION = 'authorization';

    public const string EVENT_AUTHORIZATION_POST = 'authorization.post';

    protected ?Result $authenticationResult = null;

    /**
     * Whether or not authorization has completed/succeeded
     */
    protected bool $authorized = false;

    /**
     * The resource used for authorization queries
     */
    protected ?string $resource = null;

    /**
     * @param mixed $authentication
     * @param mixed $authorization
     */
    public function __construct(
        protected MvcEvent $mvcEvent,
        protected $authentication,
        protected $authorization,
    ) {}

    public function getAuthenticationService(): mixed
    {
        return $this->authentication;
    }

    public function hasAuthenticationResult(): bool
    {
        return $this->authenticationResult !== null;
    }

    public function setAuthenticationResult(Result $result): static
    {
        $this->authenticationResult = $result;
        return $this;
    }

    public function getAuthenticationResult(): ?Result
    {
        return $this->authenticationResult;
    }

    public function getAuthorizationService(): mixed
    {
        return $this->authorization;
    }

    public function getMvcEvent(): MvcEvent
    {
        return $this->mvcEvent;
    }

    public function getIdentity(): mixed
    {
        return Value::call($this->authentication, 'getIdentity');
    }

    public function setIdentity(IdentityInterface $identity): static
    {
        Value::call(Value::call($this->authentication, 'getStorage'), 'write', [$identity]);
        return $this;
    }

    public function getResource(): ?string
    {
        return $this->resource;
    }

    public function setResource(?string $resource): static
    {
        $this->resource = $resource;
        return $this;
    }

    public function isAuthorized(): bool
    {
        return $this->authorized;
    }

    public function setIsAuthorized(bool $flag): static
    {
        $this->authorized = $flag;
        return $this;
    }
}
