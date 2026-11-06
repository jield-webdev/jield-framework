<?php

declare(strict_types=1);

namespace Jield\ApiTools\OAuth2;

interface TokenExchangeInterface
{
    public function exchange(array $data): array;
}
