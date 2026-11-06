<?php

declare(strict_types=1);

namespace Jield\Search\Message;

use Jield\Search\Service\SearchServiceInterface;
use Webmozart\Assert\Assert;

final readonly class UpdateSearchEntitiesMessage
{
    public function __construct(
        private string $entityClassName,
        private array $entityIds,
        private array $searchServices,
    ) {
        //All search services have to implement the SearchServiceInterface
        Assert::allImplementsInterface(value: $searchServices, interface: SearchServiceInterface::class);

        //All entity ids have to be integers
        Assert::allInteger(value: $entityIds);
    }

    public function getEntityClassName(): string
    {
        return $this->entityClassName;
    }

    public function getEntityIds(): array
    {
        return $this->entityIds;
    }

    public function getSearchServices(): array
    {
        return $this->searchServices;
    }
}
