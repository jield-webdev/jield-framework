<?php

namespace Assetic\Contracts\Cache;

interface CacheInterface
{
    /** @return bool The vendor annotation uses the unqualified name Boolean. */
    public function has($key);
}
