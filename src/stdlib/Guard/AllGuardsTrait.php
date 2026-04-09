<?php

declare(strict_types=1);

namespace Jield\Stdlib\Guard;

/**
 * An aggregate for all guard traits
 */
trait AllGuardsTrait
{
    use ArrayOrTraversableGuardTrait;
    use EmptyGuardTrait;
    use NullGuardTrait;
}
