<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation\Exception;

use InvalidArgumentException;

class InvalidContentStreamException extends InvalidArgumentException implements ExceptionInterface
{
}
