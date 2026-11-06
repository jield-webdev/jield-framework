<?php

declare(strict_types=1);

namespace Jield\GoogleAnalytics\Analytics;

use Jield\GoogleAnalytics\Analytics\Ecommerce\Transaction;
use Jield\GoogleAnalytics\Exception\InvalidArgumentException;

class Tracker
{
    /** @var array<int, CustomVariable> */
    protected array $customVariables = [];

    /** @var list<Event> */
    protected array $events = [];

    /** @var array<int, Transaction> */
    protected array $transactions = [];

    public function __construct(
        protected string $id,
        protected bool $enableTracking = true,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function setId($id): void
    {
        $this->id = (string) $id;
    }

    public function isEnabled(): bool
    {
        return $this->enableTracking;
    }

    /** @return array<int, CustomVariable> */
    public function getCustomVariables(): array
    {
        return $this->customVariables;
    }

    public function addCustomVariable(CustomVariable $variable): void
    {
        $index = $variable->getIndex();
        if (array_key_exists(key: $index, array: $this->customVariables)) {
            throw new InvalidArgumentException(message: sprintf(
                'Cannot add custom variable with index %d, it already exists',
                $index,
            ));
        }

        $this->customVariables[$index] = $variable;
    }

    /** @return list<Event> */
    public function getEvents(): array
    {
        return $this->events;
    }

    public function addEvent(Event $event): void
    {
        $this->events[] = $event;
    }

    /** @return array<int, Transaction> */
    public function getTransactions(): array
    {
        return $this->transactions;
    }

    public function addTransaction(Transaction $transaction): void
    {
        $id = $transaction->getId();
        if (array_key_exists(key: $id, array: $this->transactions)) {
            throw new InvalidArgumentException(message: sprintf(
                'Cannot add transaction with id %s, it already exists',
                $id,
            ));
        }

        $this->transactions[$id] = $transaction;
    }
}
