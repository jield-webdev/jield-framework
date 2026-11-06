<?php

declare(strict_types=1);

namespace Jield\Export\Options;

use Jield\Export\Entity\StorageLocation;
use Laminas\Stdlib\AbstractOptions;

/**
 * @extends \Laminas\Stdlib\AbstractOptions<mixed>
 */
class ModuleOptions extends AbstractOptions
{
    protected array $entities = [];
    protected string $storageLocationEntity = StorageLocation::class;

    public function getEntities(): array
    {
        return $this->entities;
    }

    public function setEntities(array $entities): void
    {
        $this->entities = $entities;
    }

    public function getStorageLocationEntity(): string
    {
        return $this->storageLocationEntity;
    }

    public function setStorageLocationEntity(string $storageLocationEntity): void
    {
        $this->storageLocationEntity = $storageLocationEntity;
    }
}
