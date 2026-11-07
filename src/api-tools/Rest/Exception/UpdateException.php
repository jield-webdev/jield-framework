<?php

declare(strict_types=1);

namespace Jield\ApiTools\Rest\Exception;

use Jield\ApiTools\ApiProblem\Exception\DomainException;

/**
 * Throw this exception from a "update" resource listener in order to indicate
 * an inability to update an item and automatically report it.
 */
class UpdateException extends DomainException
{
}
