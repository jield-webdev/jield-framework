<?php

declare(strict_types=1);

namespace Jield\Authorize\Exception;

use BadMethodCallException;

/**
 * Exception to be thrown in case of unauthorized access to a resource
 */
class UnAuthorizedException extends BadMethodCallException {}
