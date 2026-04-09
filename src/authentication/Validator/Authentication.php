<?php

declare(strict_types=1);

namespace Jield\Authentication\Validator;

use Jield\Authentication\Adapter\ValidatableAdapterInterface;
use Jield\Authentication\AuthenticationService;
use Jield\Authentication\Exception;
use Jield\Authentication\Result;
use Jield\Stdlib\ArrayUtils;
use Laminas\Validator\AbstractValidator;
use Override;
use Traversable;
use function array_key_exists;
use function get_debug_type;
use function is_array;
use function is_string;
use function sprintf;

/**
 * Authentication Validator
 */
class Authentication extends AbstractValidator
{
    /**
     * Error codes
     *
     * @const string
     */
    public const string IDENTITY_NOT_FOUND = 'identityNotFound';
    public const string IDENTITY_AMBIGUOUS = 'identityAmbiguous';
    public const string CREDENTIAL_INVALID = 'credentialInvalid';
    public const string UNCATEGORIZED = 'uncategorized';
    public const string GENERAL = 'general';

    /**
     * Authentication\Result codes mapping
     *
     * @const array
     */
    public const array CODE_MAP
        = [
            Result::FAILURE_IDENTITY_NOT_FOUND => self::IDENTITY_NOT_FOUND,
            Result::FAILURE_CREDENTIAL_INVALID => self::CREDENTIAL_INVALID,
            Result::FAILURE_IDENTITY_AMBIGUOUS => self::IDENTITY_AMBIGUOUS,
            Result::FAILURE_UNCATEGORIZED      => self::UNCATEGORIZED,
        ];

    /**
     * Authentication\Result codes mapping configurable overrides
     *
     * @var string[]
     */
    protected array $codeMap = [];

    /**
     * Error Messages
     *
     * @var array
     */
    protected array $messageTemplates
        = [
            self::IDENTITY_NOT_FOUND => 'Invalid identity',
            self::IDENTITY_AMBIGUOUS => 'Identity is ambiguous',
            self::CREDENTIAL_INVALID => 'Invalid password',
            self::UNCATEGORIZED      => 'Authentication failed',
            self::GENERAL            => 'Authentication failed',
        ];

    /**
     * Authentication Adapter
     *
     * @var null|ValidatableAdapterInterface
     */
    protected ?ValidatableAdapterInterface $adapter;

    /**
     * Identity (or field)
     *
     * @var string
     */
    protected string $identity;

    /**
     * Credential (or field)
     *
     * @var string
     */
    protected string $credential;

    /**
     * Authentication Service
     *
     * @var null|AuthenticationService
     */
    protected ?AuthenticationService $service;

    /**
     * Sets validator options
     *
     * @param array<string, mixed>|Traversable<string, mixed> $options
     */
    public function __construct($options = null)
    {
        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray(iterator: $options);
        }

        if (is_array(value: $options)) {
            if (isset($options['adapter'])) {
                $this->setAdapter(adapter: $options['adapter']);
            }
            if (isset($options['identity'])) {
                $this->setIdentity(identity: $options['identity']);
            }
            if (isset($options['credential'])) {
                $this->setCredential(credential: $options['credential']);
            }
            if (isset($options['service'])) {
                $this->setService(service: $options['service']);
            }
            if (isset($options['code_map'])) {
                foreach ($options['code_map'] as $code => $template) {
                    if (empty($template) || !is_string(value: $template)) {
                        throw new Exception\InvalidArgumentException(
                            message: 'Message key in code_map option must be a non-empty string'
                        );
                    }
                    if (!isset($this->messageTemplates[$template])) {
                        $this->messageTemplates[$template] = $this->messageTemplates[static::GENERAL];
                    }
                    $this->codeMap[(int)$code] = $template;
                }
            }
        }
        parent::__construct(options: $options);
    }

    /**
     * Get Adapter
     *
     * @return null|ValidatableAdapterInterface
     */
    public function getAdapter(): ?ValidatableAdapterInterface
    {
        return $this->adapter;
    }

    /**
     * Set Adapter
     *
     * @return self Provides a fluent interface
     */
    public function setAdapter(ValidatableAdapterInterface $adapter): static
    {
        $this->adapter = $adapter;

        return $this;
    }

    /**
     * Get Identity
     *
     * @return mixed
     */
    public function getIdentity(): mixed
    {
        return $this->identity;
    }

    /**
     * Set Identity
     *
     * @param mixed $identity
     * @return self Provides a fluent interface
     */
    public function setIdentity(mixed $identity): static
    {
        $this->identity = $identity;
        return $this;
    }

    /**
     * Get Credential
     *
     * @return mixed
     */
    public function getCredential(): mixed
    {
        return $this->credential;
    }

    /**
     * Set Credential
     *
     * @param mixed $credential
     * @return self Provides a fluent interface
     */
    public function setCredential(mixed $credential): static
    {
        $this->credential = $credential;

        return $this;
    }

    /**
     * Get Service
     *
     * @return null|AuthenticationService
     */
    public function getService(): ?AuthenticationService
    {
        return $this->service;
    }

    /**
     * Set Service
     *
     * @return self Provides a fluent interface
     */
    public function setService(AuthenticationService $service): static
    {
        $this->service = $service;

        return $this;
    }

    /**
     * Returns true if and only if authentication result is valid
     *
     * If authentication result fails validation, then this method returns false, and
     * getMessages() will return an array of messages that explain why the
     * validation failed.
     *
     * @param null|mixed $value OPTIONAL Credential (or field)
     * @param array|null $context OPTIONAL Authentication data (identity and/or credential)
     * @return bool
     * @throws Exception\RuntimeException
     */
    #[Override]
    public function isValid($value = null, ?array $context = null): bool
    {
        if ($value !== null) {
            $this->setCredential(credential: $value);
        }

        if ($this->identity === null) {
            throw new Exception\RuntimeException(message: 'Identity must be set prior to validation');
        }

        $identity = ($context !== null) && array_key_exists(key: $this->identity, array: $context)
            ? $context[$this->identity]
            : $this->identity;

        if ($this->credential === null) {
            throw new Exception\RuntimeException(message: 'Credential must be set prior to validation');
        }

        $credential = ($context !== null) && array_key_exists(key: $this->credential, array: $context)
            ? $context[$this->credential]
            : $this->credential;

        if (!$this->service) {
            throw new Exception\RuntimeException(message: 'AuthenticationService must be set prior to validation');
        }

        $adapter = $this->adapter ?: $this->getAdapterFromAuthenticationService();
        $adapter->setIdentity(identity: $identity);
        $adapter->setCredential(credential: $credential);

        $result = $this->service->authenticate(adapter: $adapter);

        if ($result->isValid()) {
            return true;
        }

        $messageKey = $this->mapResultCodeToMessageKey(code: $result->getCode());
        $this->error(messageKey: $messageKey);

        return false;
    }

    /**
     * @param int $code Authentication result code
     * @return string Message key that should be used for the code
     */
    protected function mapResultCodeToMessageKey(int $code): string
    {
        if (isset($this->codeMap[$code])) {
            return $this->codeMap[$code];
        }
        if (array_key_exists(key: $code, array: static::CODE_MAP)) {
            return static::CODE_MAP[$code];
        }
        return self::GENERAL;
    }

    /**
     * @return ValidatableAdapterInterface
     * @throws Exception\RuntimeException If no adapter present in
     *     authentication service.
     * @throws Exception\RuntimeException If adapter present in authentication
     *     service is not a ValidatableAdapterInterface instance.
     */
    private function getAdapterFromAuthenticationService(): ValidatableAdapterInterface
    {
        if (!$this->service) {
            throw new Exception\RuntimeException(message: 'Adapter must be set prior to validation');
        }

        $adapter = $this->service->getAdapter();
        if (!$adapter) {
            throw new Exception\RuntimeException(message: 'Adapter must be set prior to validation');
        }

        if (!$adapter instanceof ValidatableAdapterInterface) {
            throw new Exception\RuntimeException(message: sprintf(
                'Adapter must be an instance of %s; %s given',
                ValidatableAdapterInterface::class,
                get_debug_type(value: $adapter)
            ));
        }

        return $adapter;
    }
}
