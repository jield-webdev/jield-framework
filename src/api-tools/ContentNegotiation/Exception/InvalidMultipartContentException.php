<?php

declare(strict_types=1);

namespace Jield\ApiTools\ContentNegotiation\Exception;

use DomainException;

class InvalidMultipartContentException extends DomainException implements ExceptionInterface
{
}
