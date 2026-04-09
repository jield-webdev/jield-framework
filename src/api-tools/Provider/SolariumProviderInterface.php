<?php

declare(strict_types=1);

namespace Jield\ApiTools\Provider;

use Solarium\QueryType\Select\Result\Document;

interface SolariumProviderInterface
{
    public function generateArrayFromSearchDocument(Document $document): array;
}
