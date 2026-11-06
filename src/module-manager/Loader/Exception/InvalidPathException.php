<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Loader\Exception;

use Exception;

require_once __DIR__ . '/ExceptionInterface.php';

class InvalidPathException extends Exception implements ExceptionInterface {}
