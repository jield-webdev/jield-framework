<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation\Exception;

use RuntimeException;

class InvalidJsonException extends RuntimeException implements ExceptionInterface
{
}
