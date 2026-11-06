<?php

declare(strict_types=1);

namespace Jield\Export\Entity;

use Jield\Export\Enum\ExportFileTypeEnum;
use Jield\Export\Enum\TypeEnum;

interface StorageLocationInterface
{
    public function getId(): ?int;

    public function getName(): string;

    public function getConnectionString(): string;

    public function getFolder(): string;

    public function getExportFileType(): ExportFileTypeEnum;

    public function getType(): TypeEnum;

    public function getContainer(): string;
}
