<?php

declare(strict_types=1);

namespace Jield\Export\Entity;

use Doctrine\ORM\Mapping as ORM;
use Jield\Export\Enum\ExportFileTypeEnum;
use Jield\Export\Enum\TypeEnum;
use Override;

#[ORM\Table]
class StorageLocation implements StorageLocationInterface
{
    #[ORM\Column(type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private ?int $id = null;

    #[ORM\Column]
    private string $name = '';

    #[ORM\Column(length: 2000)]
    private string $connectionString = '';

    #[ORM\Column(enumType: ExportFileTypeEnum::class)]
    private ExportFileTypeEnum $exportFileType = ExportFileTypeEnum::PARQUET;

    #[ORM\Column]
    private string $container = '';

    #[ORM\Column]
    private string $folder = '';

    #[Override]
    public function __toString(): string
    {
        return $this->name;
    }

    public function getType(): TypeEnum
    {
        return TypeEnum::EXPORT;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getConnectionString(): string
    {
        return $this->connectionString;
    }

    public function setConnectionString(string $connectionString): void
    {
        $this->connectionString = $connectionString;
    }

    public function getExportFileType(): ExportFileTypeEnum
    {
        return $this->exportFileType;
    }

    public function setExportFileType(ExportFileTypeEnum $exportFileType): void
    {
        $this->exportFileType = $exportFileType;
    }

    public function getContainer(): string
    {
        return $this->container;
    }

    public function setContainer(string $container): void
    {
        $this->container = $container;
    }

    public function getFolder(): string
    {
        return $this->folder;
    }

    public function setFolder(string $folder): void
    {
        $this->folder = $folder;
    }
}
