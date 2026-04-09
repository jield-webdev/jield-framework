<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Extractor;

use Jield\ApiTools\ApiProblem\Exception\DomainException;
use Jield\ApiTools\Hal\Link\Link;
use Jield\ApiTools\Hal\Link\LinkCollection;

use Override;
use function is_array;
use function sprintf;

class LinkCollectionExtractor implements LinkCollectionExtractorInterface
{
    /** @var LinkExtractorInterface */
    protected LinkExtractorInterface $linkExtractor;

    public function __construct(LinkExtractorInterface $linkExtractor)
    {
        $this->setLinkExtractor(linkExtractor: $linkExtractor);
    }

    /**
     * @return LinkExtractorInterface
     */
    #[Override]
    public function getLinkExtractor(): LinkExtractorInterface
    {
        return $this->linkExtractor;
    }

    public function setLinkExtractor(LinkExtractorInterface $linkExtractor): void
    {
        $this->linkExtractor = $linkExtractor;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function extract(LinkCollection $collection): array
    {
        $links = [];
        foreach ($collection as $rel => $linkDefinition) {
            if ($linkDefinition instanceof Link) {
                $links[$rel] = $this->linkExtractor->extract(link: $linkDefinition);
                continue;
            }

            if (! is_array(value: $linkDefinition)) {
                throw new DomainException(message: sprintf(
                    'Link object for relation "%s" in resource was malformed; cannot generate link',
                    $rel
                ));
            }

            $aggregate = [];
            /** @var mixed $subLink */
            foreach ($linkDefinition as $subLink) {
                if (! $subLink instanceof Link) {
                    throw new DomainException(message: sprintf(
                        'Link object aggregated for relation "%s" in resource was malformed; cannot generate link',
                        $rel
                    ));
                }

                $aggregate[] = $this->linkExtractor->extract(link: $subLink);
            }

            $links[$rel] = $aggregate;
        }

        return $links;
    }
}
