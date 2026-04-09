<?php

declare(strict_types=1);

namespace Jield\ApiTools\OAuth2\Adapter\Exception;

use Jield\ApiTools\OAuth2\ExceptionInterface;

class RuntimeException extends \RuntimeException implements
    ExceptionInterface
{
}
