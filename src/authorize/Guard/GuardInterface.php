<?php

declare(strict_types=1);

namespace Jield\Authorize\Guard;

use Laminas\EventManager\ListenerAggregateInterface;

/**
 * Interface for generic guard listeners
 */
interface GuardInterface extends ListenerAggregateInterface
{
}
