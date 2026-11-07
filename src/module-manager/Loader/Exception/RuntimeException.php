<?php

declare(strict_types=1);

namespace Jield\ModuleManager\Loader\Exception;

require_once __DIR__ . '/ExceptionInterface.php';

class RuntimeException extends \RuntimeException implements ExceptionInterface
{
}
