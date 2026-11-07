<?php

declare(strict_types=1);

namespace Jield\ApiTools\Rest;

use ArrayAccess;
use Jield\ApiTools\MvcAuth\Identity\IdentityInterface;
use Jield\Mvc\Router\RouteMatch;
use Laminas\EventManager\Event;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\Stdlib\Parameters;
use Laminas\Stdlib\RequestInterface;
use Override;
use Webmozart\Assert\Assert;

use function is_array;

/**
 * @extends Event<object|string|null, array|ArrayAccess|object>
 */
class ResourceEvent extends Event
{
    protected ?IdentityInterface $identity = null;
    protected ?InputFilterInterface $inputFilter = null;
    protected ?Parameters $queryParams = null;
    protected ?RequestInterface $request = null;
    protected ?RouteMatch $routeMatch = null;

    /**
     * Overload setParams to inject request object, if passed via params
     *
     * @template NewTParams of array|ArrayAccess|object
     * @param NewTParams $params
     * @phpstan-self-out static
     *
     **/
    #[Override]
    public function setParams($params)
    {
        parent::setParams(params: $params);
        $params = parent::getParams();
        if ((is_array(value: $params) || $params instanceof ArrayAccess) && ($params['request'] ?? null) !== null) {
            $this->setRequest(request: Assert::isInstanceOf($params['request'], RequestInterface::class));
            unset($params['request']);
        }

        parent::setParams(params: $params);
    }

    public function setIdentity(?IdentityInterface $identity = null): static
    {
        $this->identity = $identity;
        return $this;
    }

    public function getIdentity(): ?IdentityInterface
    {
        return $this->identity;
    }

    public function setInputFilter(?InputFilterInterface $inputFilter = null): static
    {
        $this->inputFilter = $inputFilter;
        return $this;
    }

    public function getInputFilter(): ?InputFilterInterface
    {
        return $this->inputFilter;
    }

    public function setQueryParams(?Parameters $params = null): static
    {
        $this->queryParams = $params;
        return $this;
    }

    public function getQueryParams(): ?Parameters
    {
        return $this->queryParams;
    }

    /**
     * Retrieve a single query parameter by name
     *
     * If not present, returns the $default value provided.
     */
    public function getQueryParam(string $name, mixed $default = null): mixed
    {
        $params = $this->getQueryParams();
        if (!$params instanceof \Laminas\Stdlib\Parameters) {
            return $default;
        }

        return $params->get($name, default: $default);
    }

    public function setRequest(?RequestInterface $request = null): static
    {
        $this->request = $request;
        return $this;
    }

    public function getRequest(): ?RequestInterface
    {
        return $this->request;
    }

    public function setRouteMatch(?RouteMatch $matches = null): static
    {
        $this->routeMatch = $matches;
        return $this;
    }

    public function getRouteMatch(): ?RouteMatch
    {
        return $this->routeMatch;
    }

    /**
     * Retrieve a single route match parameter by name.
     *
     * If not present, returns the $default value provided.
     */
    public function getRouteParam(string $name, mixed $default = null): mixed
    {
        $matches = $this->getRouteMatch();
        if (null === $matches) {
            return $default;
        }

        return $matches->getParam(name: $name, default: $default);
    }
}
