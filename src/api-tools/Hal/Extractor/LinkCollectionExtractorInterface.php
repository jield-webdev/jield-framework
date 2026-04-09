<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Extractor;

use Jield\ApiTools\Hal\Link\LinkCollection;

interface LinkCollectionExtractorInterface
{
    /**
     * Extract a link collection into a structured set of links.
     *
     */
    public function extract(LinkCollection $collection): array;

    /**
     * @return LinkExtractorInterface
     */
    public function getLinkExtractor(): LinkExtractorInterface;
}
