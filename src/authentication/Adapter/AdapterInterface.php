<?php

declare(strict_types=1);

namespace Jield\Authentication\Adapter;

use Jield\Authentication\Adapter\Exception\ExceptionInterface;
use Jield\Authentication\Result;

interface AdapterInterface
{
    /**
     * Performs an authentication attempt
     *
     * @return Result
     * @throws ExceptionInterface If authentication cannot be performed.
     */
    public function authenticate(): Result;
}
