<?php

declare(strict_types=1);

namespace Jield\Search\Message;

use Jield\Search\Service\SearchServiceInterface;
use Webmozart\Assert\Assert;

final readonly class UpdateSearchIndexMessage
{
    public function __construct(
        private string $entityClassName,
        private array $searchServices,
    ) {
        //All search services have to implement the SearchServiceInterface
        Assert::allImplementsInterface(value: $searchServices, interface: SearchServiceInterface::class);
    }

    public function getEntityClassName(): string
    {
        return $this->entityClassName;
    }

    public function getSearchServices(): array
    {
        return $this->searchServices;
    }
}
