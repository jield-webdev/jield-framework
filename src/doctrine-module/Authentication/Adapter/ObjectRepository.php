<?php

declare(strict_types=1);

namespace DoctrineModule\Authentication\Adapter;

use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;
use DoctrineModule\Options\Authentication as AuthenticationOptions;
use Jield\Stdlib\Value;
use Laminas\Authentication\Adapter\AbstractAdapter;
use Laminas\Authentication\Adapter\Exception;
use Laminas\Authentication\Result as AuthenticationResult;

use function call_user_func;
use function method_exists;
use function property_exists;
use function sprintf;

/**
 * Authentication adapter that uses a Doctrine object for verification.
 */
class ObjectRepository extends AbstractAdapter
{
    protected AuthenticationOptions $options;

    /**
     * Contains the authentication results.
     *
     * @var array
     */
    protected ?array $authenticationResultInfo = null;

    protected Inflector $inflector;

    /**
     * Constructor
     *
     * @param array|AuthenticationOptions $options
     */
    public function __construct(array|AuthenticationOptions $options = [], ?Inflector $inflector = null)
    {
        $this->setOptions(options: $options);
        $this->inflector = $inflector ?? InflectorFactory::create()->build();
    }

    /** @param array|AuthenticationOptions $options */
    public function setOptions(array|AuthenticationOptions $options): self
    {
        if (!$options instanceof AuthenticationOptions) {
            $options = new AuthenticationOptions(options: Value::stringKeyedArray($options));
        }

        $this->options = $options;

        return $this;
    }

    public function getOptions(): AuthenticationOptions
    {
        return $this->options;
    }

    public function authenticate(): AuthenticationResult
    {
        $this->setup();
        $options = $this->options;
        $identity = $options
            ->getObjectRepository()
            ->findOneBy(criteria: [$options->getIdentityProperty() => $this->identity]);

        if (!$identity) {
            $this->authenticationResultInfo['code'] = AuthenticationResult::FAILURE_IDENTITY_NOT_FOUND;
            Value::setPath(
                $this->authenticationResultInfo,
                ['messages', null],
                'A record with the supplied identity could not be found.',
            );

            return $this->createAuthenticationResult();
        }

        return $this->validateIdentity(identity: $identity);
    }

    /**
     * This method attempts to validate that the record in the resultset is indeed a
     * record that matched the identity provided to this adapter.
     *
     * @throws Exception\UnexpectedValueException
     */
    protected function validateIdentity(object $identity): AuthenticationResult
    {
        $credentialProperty = $this->options->getCredentialProperty();
        $getter = 'get' . $this->inflector->classify(word: $credentialProperty);

        if (method_exists(object_or_class: $identity, method: $getter)) {
            $documentCredential = Value::call($identity, $getter);
        } elseif (property_exists(object_or_class: $identity, property: $credentialProperty)) {
            $documentCredential = Value::property($identity, $credentialProperty);
        } else {
            throw new Exception\UnexpectedValueException(message: sprintf(
                'Property (%s) in (%s) is not accessible. You should implement %s::%s()',
                $credentialProperty,
                $identity::class,
                $identity::class,
                $getter,
            ));
        }

        $credentialValue = $this->credential;
        $callable = $this->options->getCredentialCallable();

        if ($callable) {
            /** @var mixed $credentialValue */
            $credentialValue = call_user_func(Value::callback($callable), $identity, $credentialValue);
        }

        if ($credentialValue !== true && $credentialValue !== $documentCredential) {
            $this->authenticationResultInfo['code'] = AuthenticationResult::FAILURE_CREDENTIAL_INVALID;
            Value::setPath(
                $this->authenticationResultInfo,
                ['messages', null],
                'Supplied credential is invalid.',
            );

            return $this->createAuthenticationResult();
        }

        $this->authenticationResultInfo['code'] = AuthenticationResult::SUCCESS;
        $this->authenticationResultInfo['identity'] = $identity;
        Value::setPath($this->authenticationResultInfo, ['messages', null], 'Authentication successful.');

        return $this->createAuthenticationResult();
    }

    /**
     * This method abstracts the steps involved with making sure that this adapter was
     * indeed setup properly with all required pieces of information.
     *
     * @throws Exception\RuntimeException In the event that setup was not
     *                                    done properly throw exception.
     */
    protected function setup(): void
    {
        if ($this->identity === null) {
            throw new Exception\RuntimeException(message: 'A value for the identity was not provided prior to authentication with ObjectRepository '
            . 'authentication adapter');
        }

        if ($this->credential === null) {
            throw new Exception\RuntimeException(message: 'A credential value was not provided prior to authentication with ObjectRepository'
            . ' authentication adapter');
        }

        $this->authenticationResultInfo = [
            'code' => AuthenticationResult::FAILURE,
            'identity' => $this->identity,
            'messages' => [],
        ];
    }

    /**
     * Creates a Laminas\Authentication\Result object from the information that has been collected
     * during the authenticate() attempt.
     */
    protected function createAuthenticationResult(): AuthenticationResult
    {
        $info = Value::array($this->authenticationResultInfo);
        return new AuthenticationResult(
            code: Value::int($info['code']),
            identity: $info['identity'],
            messages: Value::array($info['messages']),
        );
    }
}
