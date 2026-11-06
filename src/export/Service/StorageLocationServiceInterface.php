<?php

declare(strict_types=1);

namespace Jield\Export\Service;

use AzureOSS\Storage\Blob\BlobRestProxy;
use Jield\Export\Entity\StorageLocationInterface;

interface StorageLocationServiceInterface
{
    public function getDefaultStorageLocation(): StorageLocationInterface;

    public function getBlobService(): BlobRestProxy;
}
