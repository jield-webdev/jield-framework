<?php

namespace Laminas\View\Helper;

class Partial
{
    /** @return ($name is null ? self : string) */
    public function __invoke(
        string|\Laminas\View\Model\ModelInterface|null $name = null,
        iterable|object|null $values = null,
    ): string|self {}
}
