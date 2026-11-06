<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Authentication;

use Jield\ApiTools\MvcAuth\Identity;
use Jield\ApiTools\MvcAuth\MvcAuthEvent;
use Laminas\Authentication\Adapter\Http as HttpAuth;
use Laminas\Authentication\AuthenticationServiceInterface;
use Laminas\Http\Request;
use Laminas\Http\Response;
use Override;

use function array_shift;
use function in_array;
use function is_array;
use function is_string;
use function method_exists;

class HttpAdapter extends AbstractAdapter
{
    /**
     * Authorization header token types this adapter can fulfill.
     */
    protected array $authorizationTokenTypes = ['basic', 'digest'];

    /**
     * Base to use when prefixing "provides" strings
     */
    private ?string $providesBase = null;

    /**
     * @param string|null $providesBase
     */
    public function __construct(
        private readonly HttpAuth $httpAuth,
        private readonly AuthenticationServiceInterface $authenticationService,
        ?string $providesBase = null,
    ) {
        if (is_string(value: $providesBase) && ($providesBase !== '' && $providesBase !== '0')) {
            $this->providesBase = $providesBase;
        }
    }

    /**
     * Returns the "types" this adapter can handle.
     *
     * If no $providesBase is present, returns "basic" and/or "digest" in the
     * array, based on what resolvers are present in the adapter; if
     * $providesBase is present, the same strings are returned, only with the
     * $providesBase prefixed, along with a "-" separator.
     *
     * @return array Array of types this adapter can handle.
     */
    #[Override]
    public function provides(): array
    {
        $providesBase = $this->providesBase ? $this->providesBase . '-' : '';
        $provides = [];

        if (null !== $this->httpAuth->getBasicResolver()) {
            $provides[] = $providesBase . 'basic';
        }

        if (null !== $this->httpAuth->getDigestResolver()) {
            $provides[] = $providesBase . 'digest';
        }

        return $provides;
    }

    /**
     * Match the requested authentication type against what we provide.
     */
    #[Override]
    public function matches(string $type): bool
    {
        return $this->providesBase === $type || in_array(needle: $type, haystack: $this->provides(), strict: true);
    }

    /**
     * Perform pre-flight authentication operations.
     *
     * If invoked, issues a client challenge.
     */
    #[Override]
    public function preAuth(Request $request, Response $response): void
    {
        $this->httpAuth->setRequest(request: $request);
        $this->httpAuth->setResponse(response: $response);
        $this->httpAuth->challengeClient();
    }

    /**
     * Attempt to authenticate the current request.
     */
    #[Override]
    public function authenticate(
        Request $request,
        Response $response,
        MvcAuthEvent $mvcAuthEvent,
    ): Identity\IdentityInterface {
        if (!$request->getHeader(name: 'Authorization', default: false)) {
            // No credentials were present at all, so we just return a guest identity.
            return new Identity\GuestIdentity();
        }

        $this->httpAuth->setRequest(request: $request);
        $this->httpAuth->setResponse(response: $response);

        if (method_exists($this->authenticationService, 'setAdapter')) {
            $this->authenticationService->setAdapter($this->httpAuth);
        }
        $result = $this->authenticationService->authenticate();
        $mvcAuthEvent->setAuthenticationResult(result: $result);

        if (!$result->isValid()) {
            return new Identity\GuestIdentity();
        }

        /** @var mixed $resultIdentity */
        $resultIdentity = $result->getIdentity();

        // Pass fully discovered identity to AuthenticatedIdentity instance
        $identity = new Identity\AuthenticatedIdentity(identity: $resultIdentity);

        // But determine the name separately
        $name = $resultIdentity;
        if (is_array(value: $resultIdentity)) {
            /** @var mixed $name */
            $name = $resultIdentity['username'] ?? (string) array_shift(array: $resultIdentity);
        }

        $identity->setName(name: \Jield\Stdlib\Value::string($name));

        return $identity;
    }
}
