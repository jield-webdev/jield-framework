<?php

declare(strict_types=1);

namespace Jield\Mvc\Plugin\Identity;

use Jield\Mvc\Controller\Plugin\AbstractPlugin;
use Laminas\Authentication\AuthenticationServiceInterface;

/**
 * Controller plugin to fetch the authenticated identity.
 *
 * @final This class is not intended to be open for extension.
 */
class Identity extends AbstractPlugin
{
    protected ?AuthenticationServiceInterface $authenticationService = null;

    /**
     * @throws Exception\RuntimeException
     */
    public function __invoke()
    {
        if (!$this->authenticationService instanceof AuthenticationServiceInterface) {
            throw new Exception\RuntimeException(
                'No AuthenticationServiceInterface instance provided; cannot lookup identity',
            );
        }

        if (!$this->authenticationService->hasIdentity()) {
            return null;
        }

        return $this->authenticationService->getIdentity();
    }

    public function getAuthenticationService(): ?AuthenticationServiceInterface
    {
        return $this->authenticationService;
    }

    public function setAuthenticationService(?AuthenticationServiceInterface $authenticationService): Identity
    {
        $this->authenticationService = $authenticationService;
        return $this;
    }
}
