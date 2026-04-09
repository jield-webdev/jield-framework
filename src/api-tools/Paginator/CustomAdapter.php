<?php

declare(strict_types=1);

namespace Jield\ApiTools\Paginator;

use Jield\ApiTools\Provider\ProviderInterface;
use Laminas\Paginator\Adapter\ArrayAdapter;
use Override;

class CustomAdapter extends ArrayAdapter
{
    private ProviderInterface $provider;

    public function setProvider(ProviderInterface $provider): CustomAdapter
    {
        $this->provider = $provider;

        return $this;
    }

    /**
     * @param int $offset
     * @param int $itemCountPerPage
     */
    #[Override]
    public function getItems(int $offset, int $itemCountPerPage): array
    {
        foreach ($this->array as &$item) {
            $item = $this->provider->generateArray(entity: $item);
        }

        return parent::getItems(offset: $offset, itemCountPerPage: $itemCountPerPage);
    }
}