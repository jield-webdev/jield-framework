<?php

declare(strict_types=1);

namespace Jield\Mvc\Plugin\FlashMessenger;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Jield\Mvc\Controller\Plugin\AbstractPlugin;
use Laminas\Session\Container;
use Laminas\Session\ManagerInterface as Manager;
use Laminas\Stdlib\SplQueue;
use ReturnTypeWillChange;

use function assert;
use function count;

/**
 * Flash Messenger - implement session-based messages
 *
 * @template-implements IteratorAggregate<array-key, string>
 * @psalm-type MessageList = SplQueue<array-key, string>
 */
class FlashMessenger extends AbstractPlugin implements IteratorAggregate, Countable
{
    public const string NAMESPACE_DEFAULT = 'default';
    public const string NAMESPACE_SUCCESS = 'success';
    public const string NAMESPACE_WARNING = 'warning';
    public const string NAMESPACE_ERROR = 'error';
    public const string NAMESPACE_INFO = 'info';

    protected ?Container $container = null;
    /**
     * Messages from previous request
     *
     * @var array<string, MessageList>
     */
    protected array $messages = [];

    /** @var Manager|null */
    protected ?Manager $session = null;

    /**
     * Whether a message has been added during this request
     *
     * @var bool
     */
    protected bool $messageAdded = false;

    /**
     * Instance namespace, default is 'default'
     *
     * @var string
     */
    protected string $namespace = self::NAMESPACE_DEFAULT;

    /**
     * Add a message with "info" type
     *
     * @param string $message
     * @return static
     */
    public function addInfoMessage(string $message): static
    {
        $this->addMessage(message: $message, namespace: self::NAMESPACE_INFO);
        return $this;
    }

    /**
     * Add a message
     *
     * @param string $message
     * @param string|null $namespace
     * @param int $hops
     * @return static
     */
    public function addMessage(string $message, ?string $namespace = null, int $hops = 1): static
    {
        $container = $this->getContainer();

        if (null === $namespace) {
            $namespace = $this->getNamespace();
        }

        if (!$this->messageAdded) {
            $this->getMessagesFromContainer();
            $container->setExpirationHops(hops: $hops, vars: null);
        }

        if (!isset($container[$namespace]) || !$container[$namespace] instanceof SplQueue) {
            $container[$namespace] = new SplQueue();
        }

        \Jield\Stdlib\Value::call($container[$namespace], 'push', [$message]);

        $this->messageAdded = true;

        return $this;
    }

    /**
     * Get session container for flash messages
     */
    public function getContainer(): Container
    {
        if ($this->container instanceof Container) {
            return $this->container;
        }

        $manager = $this->getSessionManager();
        $this->container = new Container(name: 'FlashMessenger', manager: $manager);

        return $this->container;
    }

    /**
     * Retrieve the session manager
     *
     * If none composed, lazy-loads a SessionManager instance
     */
    public function getSessionManager(): ?Manager
    {
        if (!$this->session instanceof Manager) {
            $this->setSessionManager(manager: Container::getDefaultManager());
        }

        assert(assertion: $this->session instanceof Manager);

        return $this->session;
    }

    /**
     * Set the session manager
     *
     * @return static
     */
    public function setSessionManager(Manager $manager): static
    {
        $this->session = $manager;
        return $this;
    }

    /**
     * Get the message namespace
     *
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * Change the namespace messages are added to
     *
     * Useful for per action controller messaging between requests
     */
    public function setNamespace(string $namespace = 'default'): static
    {
        $this->namespace = $namespace;
        return $this;
    }

    /**
     * Pull messages from the session container
     *
     * Iterates through the session container, removing messages into the local
     * scope.
     *
     * @return void
     */
    protected function getMessagesFromContainer(): void
    {
        if (!empty($this->messages) || $this->messageAdded) {
            return;
        }

        $container = $this->getContainer();

        $namespaces = [];
        /** @var mixed $messages */
        foreach ($container as $namespace => $messages) {
            $this->messages[$namespace] = \Jield\Stdlib\Value::instance($messages, SplQueue::class);
            $namespaces[] = $namespace;
        }

        foreach ($namespaces as $namespace) {
            unset($container[$namespace]);
        }
    }

    public function addSuccessMessage(string $message): static
    {
        $this->addMessage(message: $message, namespace: self::NAMESPACE_SUCCESS);
        return $this;
    }

    public function addWarningMessage(string $message): static
    {
        $this->addMessage(message: $message, namespace: self::NAMESPACE_WARNING);
        return $this;
    }

    public function addErrorMessage(string $message): static
    {
        $this->addMessage(message: $message, namespace: self::NAMESPACE_ERROR);
        return $this;
    }

    public function hasInfoMessages(): bool
    {
        return $this->hasMessages(namespace: self::NAMESPACE_INFO);
    }

    public function hasMessages(?string $namespace = null): bool
    {
        if (null === $namespace) {
            $namespace = $this->getNamespace();
        }

        $this->getMessagesFromContainer();

        return isset($this->messages[$namespace]);
    }

    public function hasSuccessMessages(): bool
    {
        return $this->hasMessages(namespace: self::NAMESPACE_SUCCESS);
    }

    public function hasWarningMessages(): bool
    {
        return $this->hasMessages(namespace: self::NAMESPACE_WARNING);
    }

    public function hasErrorMessages(): bool
    {
        return $this->hasMessages(namespace: self::NAMESPACE_ERROR);
    }

    /**
     * Get messages from "info" namespace
     *
     * @return array
     */
    public function getInfoMessages(): array
    {
        return $this->getMessages(namespace: self::NAMESPACE_INFO);
    }

    /**
     * Get messages from a specific namespace
     *
     * @param string|null $namespace
     * @return array<array-key, string>
     */
    public function getMessages(?string $namespace = null): array
    {
        if (null === $namespace) {
            $namespace = $this->getNamespace();
        }

        if ($this->hasMessages(namespace: $namespace)) {
            return $this->messages[$namespace]->toArray();
        }

        return [];
    }

    /**
     * Get messages from "success" namespace
     *
     * @return array<array-key, string>
     */
    public function getSuccessMessages(): array
    {
        return $this->getMessages(namespace: self::NAMESPACE_SUCCESS);
    }

    /**
     * Get messages from "warning" namespace
     *
     * @return array<array-key, string>
     */
    public function getWarningMessages(): array
    {
        return $this->getMessages(namespace: self::NAMESPACE_WARNING);
    }

    /**
     * Get messages from "error" namespace
     *
     * @return array<array-key, string>
     */
    public function getErrorMessages(): array
    {
        return $this->getMessages(namespace: self::NAMESPACE_ERROR);
    }

    /**
     * Clear all messages from specific namespace
     *
     * @param string $namespaceToClear
     * @return bool True if messages were cleared, false if none existed
     */
    public function clearMessagesFromNamespace(string $namespaceToClear): bool
    {
        return $this->clearMessages(namespace: $namespaceToClear);
    }

    /**
     * Clear all messages from the previous request & current namespace
     *
     * @param string|null $namespace
     * @return bool True if messages were cleared, false if none existed
     */
    public function clearMessages(?string $namespace = null): bool
    {
        if (null === $namespace) {
            $namespace = $this->getNamespace();
        }

        if ($this->hasMessages(namespace: $namespace)) {
            unset($this->messages[$namespace]);
            return true;
        }

        return false;
    }

    /**
     * Clear all messages from the container
     *
     * @return bool True if messages were cleared, false if none existed
     */
    public function clearMessagesFromContainer(): bool
    {
        $this->getMessagesFromContainer();
        if (empty($this->messages)) {
            return false;
        }

        unset($this->messages);
        $this->messages = [];

        return true;
    }

    /**
     * Check to see if messages have been added to "info"
     * namespace within this request
     *
     * @return bool
     */
    public function hasCurrentInfoMessages(): bool
    {
        return $this->hasCurrentMessages(namespace: self::NAMESPACE_INFO);
    }

    /**
     * Check to see if messages have been added to the current
     * namespace within this request
     *
     * @param string|null $namespace
     * @return bool
     */
    public function hasCurrentMessages(?string $namespace = null): bool
    {
        $container = $this->getContainer();
        if (null === $namespace) {
            $namespace = $this->getNamespace();
        }

        return isset($container[$namespace]);
    }

    /**
     * Check to see if messages have been added to "success"
     * namespace within this request
     *
     * @return bool
     */
    public function hasCurrentSuccessMessages(): bool
    {
        return $this->hasCurrentMessages(namespace: self::NAMESPACE_SUCCESS);
    }

    /**
     * Check to see if messages have been added to "warning"
     * namespace within this request
     *
     * @return bool
     */
    public function hasCurrentWarningMessages(): bool
    {
        return $this->hasCurrentMessages(namespace: self::NAMESPACE_WARNING);
    }

    /**
     * Check to see if messages have been added to "error"
     * namespace within this request
     *
     * @return bool
     */
    public function hasCurrentErrorMessages(): bool
    {
        return $this->hasCurrentMessages(namespace: self::NAMESPACE_ERROR);
    }

    /**
     * Get messages that have been added to the "info"
     * namespace within this request
     *
     * @return array<array-key, string>
     */
    public function getCurrentInfoMessages(): array
    {
        return $this->getCurrentMessages(namespace: self::NAMESPACE_INFO);
    }

    /**
     * Get messages that have been added to the current
     * namespace within this request
     *
     * @param string|null $namespace
     * @return array<array-key, string>
     */
    public function getCurrentMessages(?string $namespace = null): array
    {
        if (null === $namespace) {
            $namespace = $this->getNamespace();
        }

        if ($this->hasCurrentMessages(namespace: $namespace)) {
            $container = $this->getContainer();
            return \Jield\Stdlib\Value::strings(\Jield\Stdlib\Value::call($container[$namespace], 'toArray'));
        }

        return [];
    }

    /**
     * Get messages that have been added to the "success"
     * namespace within this request
     *
     * @return array<array-key, string>
     */
    public function getCurrentSuccessMessages(): array
    {
        return $this->getCurrentMessages(namespace: self::NAMESPACE_SUCCESS);
    }

    /**
     * Get messages that have been added to the "warning"
     * namespace within this request
     *
     * @return array<array-key, string>
     */
    public function getCurrentWarningMessages(): array
    {
        return $this->getCurrentMessages(namespace: self::NAMESPACE_WARNING);
    }

    /**
     * Get messages that have been added to the "error"
     * namespace within this request
     *
     * @return array<array-key, string>
     */
    public function getCurrentErrorMessages(): array
    {
        return $this->getCurrentMessages(namespace: self::NAMESPACE_ERROR);
    }

    /**
     * Get messages that have been added to the current
     * namespace in specific namespace
     *
     * @param string $namespaceToGet
     * @return array<array-key, string>
     */
    public function getCurrentMessagesFromNamespace(string $namespaceToGet): array
    {
        return $this->getCurrentMessages(namespace: $namespaceToGet);
    }

    /**
     * Clear messages from the current namespace
     *
     * @param string $namespaceToClear
     * @return bool True if current messages were cleared from the given namespace, false if none existed.
     */
    public function clearCurrentMessagesFromNamespace(string $namespaceToClear): bool
    {
        return $this->clearCurrentMessages(namespace: $namespaceToClear);
    }

    /**
     * Clear messages from the current request and current namespace
     *
     * @param string|null $namespace
     * @return bool True if current messages were cleared, false if none existed.
     */
    public function clearCurrentMessages(?string $namespace = null): bool
    {
        if (null === $namespace) {
            $namespace = $this->getNamespace();
        }

        if ($this->hasCurrentMessages(namespace: $namespace)) {
            $container = $this->getContainer();
            unset($container[$namespace]);
            return true;
        }

        return false;
    }

    /**
     * Clear messages from the container
     *
     * @return bool True if current messages were cleared from the container, false if none existed.
     */
    public function clearCurrentMessagesFromContainer(): bool
    {
        $container = $this->getContainer();

        $namespaces = [];
        /** @var mixed $messages */
        foreach ($container as $namespace => $messages) {
            $namespaces[] = $namespace;
        }

        if (empty($namespaces)) {
            return false;
        }

        foreach ($namespaces as $namespace) {
            unset($container[$namespace]);
        }

        return true;
    }

    /**
     * Complete the IteratorAggregate interface, for iterating
     *
     * @return ArrayIterator<array-key, string>
     */
    #[ReturnTypeWillChange]
    public function getIterator(): ArrayIterator
    {
        if ($this->hasMessages()) {
            return new ArrayIterator(array: $this->getMessages());
        }

        return new ArrayIterator();
    }

    /**
     * Complete the countable interface
     *
     * @return int
     */
    #[ReturnTypeWillChange]
    public function count(): int
    {
        if ($this->hasMessages()) {
            return count(value: $this->getMessages());
        }

        return 0;
    }

    /**
     * Get messages from a specific namespace
     *
     * @param string $namespaceToGet
     * @return array<array-key, string>
     */
    public function getMessagesFromNamespace(string $namespaceToGet): array
    {
        return $this->getMessages(namespace: $namespaceToGet);
    }
}
