<?php

declare(strict_types=1);

namespace Jield\Mvc\View\Helper;

use Laminas\Authentication\AuthenticationServiceInterface;
use Laminas\View\Exception\RuntimeException;

/**
 * View helper plugin to fetch the authenticated identity.
 *
 * Maintained here as part of the Jield MVC integration.
 *
 * @final
 */
class Identity extends AbstractHelper
{
    /** @var AuthenticationServiceInterface|null */
    protected $authenticationService;

    public function __construct(?AuthenticationServiceInterface $authenticationService = null)
    {
        $this->authenticationService = $authenticationService;
    }

    /**
     * Retrieve the current identity, if any.
     *
     * If none available, returns null.
     *
     * @return mixed|null
     */
    public function __invoke()
    {
        $service = $this->authenticationService;
        if (!$service instanceof AuthenticationServiceInterface) {
            throw new RuntimeException('No AuthenticationServiceInterface instance provided');
        }

        return $service->hasIdentity() ? $service->getIdentity() : null;
    }

    /**
     * Set AuthenticationService instance
     *
     * Maintained here as part of the Jield MVC integration.
     *             be removed in version 3.0 of this component
     *
     * @return $this
     */
    public function setAuthenticationService(AuthenticationServiceInterface $authenticationService)
    {
        $this->authenticationService = $authenticationService;

        return $this;
    }

    /**
     * Get AuthenticationService instance
     *
     * Maintained here as part of the Jield MVC integration.
     *             be removed in version 3.0 of this component
     *
     * @return null|object
     */
    public function getAuthenticationService()
    {
        return $this->authenticationService;
    }
}
