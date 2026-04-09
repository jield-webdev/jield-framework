<?php

declare(strict_types=1);

namespace Jield\ApiTools\Rest;

use ArrayAccess;
use Jield\ApiTools\MvcAuth\Identity\IdentityInterface;
use Laminas\EventManager\Event;
use Laminas\EventManager\Exception\InvalidArgumentException as EventManagerInvalidArgumentException;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\Router\RouteMatch;
use Jield\Stdlib\Parameters;
use Jield\Stdlib\RequestInterface;
use Override;
use function gettype;
use function is_array;
use function is_object;
use function sprintf;

/**
 * @extends Event<object|string|null, array|ArrayAccess|object>
 */
class ResourceEvent extends Event
{
    protected ?IdentityInterface    $identity    = null;
    protected ?InputFilterInterface $inputFilter = null;
    protected ?Parameters           $queryParams = null;
    protected ?RequestInterface     $request     = null;
    protected ?RouteMatch           $routeMatch  = null;

    /**
     * Overload setParams to inject request object, if passed via params
     *
     * @param mixed $params
     */
    /** @phpstan-ignore-next-line  */
    #[Override]
    public function setParams(object|ArrayAccess|array $params): void
    {
        if (!is_array(value: $params) && !is_object(value: $params)) {
            throw new EventManagerInvalidArgumentException(message: sprintf(
                'Event parameters must be an array or object; received "%s"',
                gettype(value: $params)
            ));
        }

        if ((is_array(value: $params) || $params instanceof ArrayAccess) && isset($params['request'])) {
            $this->setRequest(request: $params['request']);
            unset($params['request']);
        }

        parent::setParams(params: $params);
        return $this;
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
     *
     * @param string $name
     */
    public function getQueryParam(string $name, mixed $default = null): mixed
    {
        $params = $this->getQueryParams();
        if (!$params instanceof \Jield\Stdlib\Parameters) {
            return $default;
        }

        return $params->get(name: $name, default: $default);
    }

    public function setRequest(?RequestInterface $request = null): static
    {
        $this->request = $request;
        return $this;
    }

    /**
     * @return null|RequestInterface
     */
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
     *
     * @param string $name
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
