<?php

declare(strict_types=1);

namespace Jield\ApiTools\Paginator;

use Jield\ApiTools\Provider\ProviderInterface;
use Laminas\Paginator\Adapter\AdapterInterface;

/**
 * @implements \Laminas\Paginator\Adapter\AdapterInterface<int, mixed>
 */
class CustomAdapter implements AdapterInterface
{
    private ProviderInterface $provider;

    public function __construct(
        private readonly array $array,
    ) {}

    public function setProvider(ProviderInterface $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    public function count(): int
    {
        return count($this->array);
    }

    public function getItems(int $offset, int $itemCountPerPage): array
    {
        return array_map($this->provider->generateArray(...), array_slice($this->array, $offset, $itemCountPerPage));
    }
}
