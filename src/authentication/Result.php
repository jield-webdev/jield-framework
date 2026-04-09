<?php

declare(strict_types=1);

namespace Jield\Authentication;

class Result
{
    /**
     * General Failure
     */
    public const int FAILURE = 0;

    /**
     * Failure due to identity not being found.
     */
    public const int FAILURE_IDENTITY_NOT_FOUND = -1;

    /**
     * Failure due to identity being ambiguous.
     */
    public const int FAILURE_IDENTITY_AMBIGUOUS = -2;

    /**
     * Failure due to invalid credential being supplied.
     */
    public const int FAILURE_CREDENTIAL_INVALID = -3;

    /**
     * Failure due to uncategorized reasons.
     */
    public const int FAILURE_UNCATEGORIZED = -4;

    /**
     * Authentication success.
     */
    public const int SUCCESS = 1;

    /**
     * Authentication result code
     *
     * @var int
     */
    protected int $code;

    /**
     * An array of string reasons why the authentication attempt was unsuccessful
     *
     * If authentication was successful, this should be an empty array.
     *
     * @var array
     */
    protected array $messages;

    /**
     * Sets the result code, identity, and failure messages
     *
     * @param int $code
     * @param  mixed   $identity
     */
    public function __construct(
        int       $code,
        /**
         * The identity used in the authentication attempt
         */
        protected $identity,
        array     $messages = []
    ) {
        $this->code     = $code;
        $this->messages = $messages;
    }

    /**
     * Returns whether the result represents a successful authentication attempt
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->code > 0;
    }

    /**
     * getCode() - Get the result code for this authentication attempt
     *
     * @return int
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * Returns the identity used in the authentication attempt
     *
     * @return mixed
     */
    public function getIdentity(): mixed
    {
        return $this->identity;
    }

    /**
     * Returns an array of string reasons why the authentication attempt was unsuccessful
     *
     * If authentication was successful, this method returns an empty array.
     *
     * @return array
     */
    public function getMessages(): array
    {
        return $this->messages;
    }
}
