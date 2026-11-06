<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Listener\Exception;

use Jield\ModuleManager\Exception;

class InvalidArgumentException extends Exception\InvalidArgumentException implements ExceptionInterface {}
