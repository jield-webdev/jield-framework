<?php

declare(strict_types=1);

namespace Jield\ApiTools\OAuth2;

final class Scope
{
    /** @return list<string> */
    public static function split(?string $value): array
    {
        $scopes = preg_split('/\s+/', trim($value ?? ''), flags: PREG_SPLIT_NO_EMPTY);
        return array_values(array_unique($scopes === false ? [] : $scopes));
    }
}
