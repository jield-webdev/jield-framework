<?php

declare(strict_types=1);

namespace Jield\Search\Message;

use Jield\Search\Service\SearchServiceInterface;
use Webmozart\Assert\Assert;

final readonly class UpdateSearchEntityMessage
{
    public function __construct(
        private string $entityClassName,
        private int $entityId,
        private array $searchServices,
    ) {
        Assert::allImplementsInterface(value: $searchServices, interface: SearchServiceInterface::class);
    }

    public function getEntityClassName(): string
    {
        return $this->entityClassName;
    }

    public function getEntityId(): int
    {
        return $this->entityId;
    }

    public function getSearchServices(): array
    {
        return $this->searchServices;
    }
}
