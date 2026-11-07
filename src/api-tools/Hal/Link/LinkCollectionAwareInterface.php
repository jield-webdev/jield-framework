<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Link;

interface LinkCollectionAwareInterface
{
    public function setLinks(LinkCollection $links): mixed;

    public function getLinks(): LinkCollection;
}
