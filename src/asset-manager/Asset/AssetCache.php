<?php

declare(strict_types=1);

namespace Jield\AssetManager\Asset;

class AssetCache extends \Assetic\Asset\AssetCache implements AssetInterface
{
    public ?string $mimetype = null;

    public function getMimetype(): ?string
    {
        return $this->mimetype;
    }

    public function setMimetype(?string $mimetype = null): AssetCache
    {
        $this->mimetype = $mimetype;
        return $this;
    }
}
