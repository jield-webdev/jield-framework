<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Link;

trait LinkCollectionAwareTrait
{
    protected ?LinkCollection $links = null;

    public function setLinks(LinkCollection $links): static
    {
        $this->links = $links;
        return $this;
    }

    /**
     * @return LinkCollection
     */
    public function getLinks(): LinkCollection
    {
        if (!$this->links instanceof LinkCollection) {
            $this->setLinks(links: new LinkCollection());
        }

        return $this->links;
    }
}
