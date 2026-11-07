<?php

declare(strict_types=1);

namespace Jield\ApiTools\Rest\Exception;

use Throwable;

/**
 * Marker interface; catch this to catch any module-specific exception.
 */
interface ExceptionInterface extends Throwable {}
