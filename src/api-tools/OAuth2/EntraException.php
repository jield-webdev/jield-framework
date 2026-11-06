<?php

declare(strict_types=1);

namespace Jield\ApiTools\OAuth2;

use RuntimeException;

final class EntraException extends RuntimeException
{
    public function __construct(
        public readonly string $error,
        string $description,
        public readonly int $status = 400,
    ) {
        parent::__construct($description);
    }
}
