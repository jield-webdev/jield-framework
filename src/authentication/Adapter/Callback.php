<?php

declare(strict_types=1);

namespace Jield\Authentication\Adapter;

use Exception;
use Jield\Authentication\Exception\InvalidArgumentException;
use Jield\Authentication\Exception\RuntimeException;
use Jield\Authentication\Result;
use Override;

use function call_user_func;
use function is_callable;

/**
 * Authentication Adapter authenticates using callback function.
 *
 * The Callback function must return an identity on authentication success,
 * and false on authentication failure.
 */
class Callback extends AbstractAdapter
{
    /** @var callable(mixed, mixed): mixed|false */
    protected $callback;

    /**
     * @param callable(mixed, mixed): mixed|false|null $callback The authentication callback
     */
    public function __construct(callable|false|null $callback = null)
    {
        if (null !== $callback) {
            $this->setCallback(callback: $callback);
        }
    }

    /**
     * Authenticate using the provided callback
     *
     * @return Result The authentication result
     * @throws \RuntimeException
     */
    #[Override]
    public function authenticate(): Result
    {
        $callback = $this->getCallback();
        if (! $callback) {
            throw new RuntimeException(message: 'No callback provided');
        }

        try {
            $identity = call_user_func($callback, $this->getIdentity(), $this->getCredential());
        } catch (Exception $e) {
            return new Result(code: Result::FAILURE_UNCATEGORIZED, identity: null, messages: [$e->getMessage()]);
        }

        if (! $identity) {
            return new Result(code: Result::FAILURE, identity: null, messages: ['Authentication failure']);
        }

        return new Result(code: Result::SUCCESS, identity: $identity, messages: ['Authentication success']);
    }

    /**
     * Gets the value of callback.
     *
     * @return null|(callable(mixed, mixed): mixed|false)
     */
    public function getCallback(): callable|false|null
    {
        return $this->callback;
    }

    /**
     * Sets the value of callback.
     *
     * @param callable(mixed, mixed): mixed|false $callback the callback
     * @return void
     *@throws InvalidArgumentException
     */
    public function setCallback(callable|false $callback): void
    {
        if (! is_callable(value: $callback)) {
            throw new InvalidArgumentException(message: 'Invalid callback provided');
        }

        $this->callback = $callback;
    }
}
