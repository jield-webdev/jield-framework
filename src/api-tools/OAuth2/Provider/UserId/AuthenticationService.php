<?php

declare(strict_types=1);

namespace Jield\ApiTools\OAuth2\Provider\UserId;

use Jield\Stdlib\Value;
use Laminas\Authentication\AuthenticationServiceInterface;
use Laminas\Stdlib\RequestInterface;
use Override;

use function is_array;
use function is_object;
use function method_exists;
use function property_exists;
use function ucfirst;

class AuthenticationService implements UserIdProviderInterface
{
    private string $userId = 'id';

    /**
     *  Set authentication service
     */
    public function __construct(
        private readonly ?AuthenticationServiceInterface $authenticationService = null,
        array $config = [],
    ) {
        if (($config['api-tools-oauth2']['user_id'] ?? null) !== null) {
            $this->userId = \Webmozart\Assert\Assert::string($config['api-tools-oauth2']['user_id']);
        }
    }

    /**
     * Use implementation of Laminas\Authentication\AuthenticationServiceInterface to fetch the identity.
     *
     * @throws \ReflectionException
     */
    #[Override]
    public function __invoke(RequestInterface $request): mixed
    {
        if (!$this->authenticationService instanceof \Laminas\Authentication\AuthenticationServiceInterface) {
            return null;
        }

        /** @mago-var mixed $identity */
        $identity = $this->authenticationService->getIdentity();

        if (is_object(value: $identity)) {
            if (property_exists(object_or_class: $identity, property: $this->userId)) {
                return Value::property($identity, $this->userId);
            }

            $method = 'get' . ucfirst(string: $this->userId);
            if (method_exists(object_or_class: $identity, method: $method)) {
                return Value::call($identity, $method);
            }

            return null;
        }

        if (is_array(value: $identity) && ($identity[$this->userId] ?? null) !== null) {
            return $identity[$this->userId];
        }

        return null;
    }
}
