<?php

declare(strict_types=1);

namespace Jield\ApiTools\OAuth2;

use Jield\Stdlib\Value;

/** Identity helpers for claims that have already passed EntraTokenValidator. */
final class EntraIdentity
{
    public static function isApplication(array $claims): bool
    {
        return ($claims['idtyp'] ?? null) === 'app';
    }

    /**
     * @param list<string> $applicationRoles Application permissions accepted by the consuming app.
     * @return list<string>
     */
    public static function scopes(array $claims, array $applicationRoles = []): array
    {
        return self::isApplication($claims)
            ? array_values(array_intersect($applicationRoles, Value::array($claims['roles'] ?? [])))
            : Scope::split(Value::nullableString($claims['scp'] ?? null));
    }

    public static function applicationSubject(string $tenantId, string $objectId): string
    {
        return 'spn:' . $tenantId . ':' . $objectId;
    }
}
