<?php

namespace Jield\Http\Client\Adapter\Exception;

use Jield\Http\Client\Exception;

class OutOfRangeException extends Exception\OutOfRangeException implements
    ExceptionInterface
{
}
