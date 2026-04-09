<?php

declare(strict_types=1);

namespace Jield\Authentication\Storage;

use Laminas\Session\Container as SessionContainer;
use Laminas\Session\ManagerInterface as SessionManager;
use Override;

class Session implements StorageInterface
{
    /**
     * Default session namespace
     */
    public const string NAMESPACE_DEFAULT = 'Jield_Auth';

    /**
     * Default session object member name
     */
    public const string MEMBER_DEFAULT = 'storage';

    /**
     * Object to proxy $_SESSION storage
     *
     * @var SessionContainer
     */
    protected SessionContainer $session;

    /**
     * Session namespace
     *
     * @var string
     */
    protected string $namespace = self::NAMESPACE_DEFAULT;

    /**
     * Session object member
     *
     * @var string
     */
    protected string $member = self::MEMBER_DEFAULT;

    /**
     * Sets session storage options and initializes session namespace object
     *
     * @param string|null $namespace
     * @param string|null $member
     */
    public function __construct(?string $namespace = null, ?string $member = null, ?SessionManager $manager = null)
    {
        if ($namespace !== null) {
            $this->namespace = $namespace;
        }
        if ($member !== null) {
            $this->member = $member;
        }
        $this->session = new SessionContainer($this->namespace, $manager);
    }

    /**
     * Returns the session namespace
     *
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * Returns the name of the session object member
     *
     * @return string
     */
    public function getMember(): string
    {
        return $this->member;
    }

    /**
     * Defined by Jield\Authentication\Storage\StorageInterface
     *
     * @return bool
     */
    #[Override]
    public function isEmpty(): bool
    {
        return ! isset($this->session->{$this->member});
    }

    /**
     * Defined by Jield\Authentication\Storage\StorageInterface
     *
     * @return mixed
     */
    #[Override]
    public function read(): mixed
    {
        return $this->session->{$this->member};
    }

    /**
     * Defined by Jield\Authentication\Storage\StorageInterface
     *
     * @param mixed $contents
     * @return void
     */
    #[Override]
    public function write(mixed $contents): void
    {
        $this->session->{$this->member} = $contents;
    }

    /**
     * Defined by Jield\Authentication\Storage\StorageInterface
     *
     * @return void
     */
    #[Override]
    public function clear(): void
    {
        unset($this->session->{$this->member});
    }
}
