<?php

declare(strict_types=1);

namespace Jield\Authentication\Adapter\Exception;

use Jield\Authentication\Exception;

class UnexpectedValueException extends Exception\UnexpectedValueException implements
    ExceptionInterface
{
}
