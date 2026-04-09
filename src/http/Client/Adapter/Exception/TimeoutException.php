<?php

namespace Jield\Http\Client\Adapter\Exception;

class TimeoutException extends RuntimeException implements ExceptionInterface
{
    public const int READ_TIMEOUT = 1000;
}
