<?php

declare(strict_types=1);

namespace Jield\ApiTools\Listener;

use DateTime;
use DateTimeInterface;
use Jield\ApiTools\ApiProblem\ApiProblem;
use Jield\ApiTools\Rest\AbstractResourceListener;

abstract class AbstractRoutedListener extends AbstractResourceListener
{
    protected static string $route;

    protected static ?string $privilege = null;

    protected static int $pageSize = 30;
    protected static int $maxPageSize = 1000;

    protected static array $entityCollectionWhitelist = [];

    /** @var array<string, list<string>> Accepted scope alternatives per HTTP method. Empty denies bearer access. */
    protected static array $scopes = [];

    /** @var list<string> Scopes explicitly allowed for application identities, in addition to $scopes. */
    protected static array $applicationScopes = [];

    protected static bool $requireAccessToken = false;

    public static function getScopes(): array
    {
        return static::$scopes;
    }

    /** @return list<string> HEAD uses the GET policy unless explicitly declared. */
    public static function getScopesForMethod(string $method): array
    {
        $method = strtoupper($method);
        if ($method === 'HEAD' && !array_key_exists('HEAD', static::$scopes)) {
            $method = 'GET';
        }
        return static::$scopes[$method] ?? [];
    }

    /** @return list<string> */
    public static function getApplicationScopes(): array
    {
        return static::$applicationScopes;
    }

    public static function requiresAccessToken(): bool
    {
        return static::$requireAccessToken;
    }

    public static function getRoute(): string
    {
        return static::$route;
    }

    public static function getPrivilege(): ?string
    {
        return static::$privilege;
    }

    public static function getPageSize(): int
    {
        return static::$pageSize;
    }

    public static function getMaxPageSize(): int
    {
        return static::$maxPageSize;
    }

    public static function getInputFilterSpecification(): array
    {
        return [];
    }

    public static function getEntityCollectionWhiteList(): array
    {
        return static::$entityCollectionWhitelist;
    }

    public static function getRouteAssertionClass(): ?string
    {
        return null;
    }

    protected function convertJSDateTimeToPHPDateTime(?string $dateTimeString): DateTime|ApiProblem|null
    {
        if (empty($dateTimeString)) {
            return null;
        }

        $dateProcessed = DateTime::createFromFormat(format: DateTimeInterface::ATOM, datetime: $dateTimeString);

        if ($dateProcessed instanceof DateTime) {
            return $dateProcessed;
        }

        $dateProcessed = DateTime::createFromFormat(
            format: DateTimeInterface::RFC3339_EXTENDED,
            datetime: $dateTimeString,
        );

        if ($dateProcessed instanceof DateTime) {
            return $dateProcessed;
        }

        $dateProcessed = DateTime::createFromFormat(format: 'Y-m-d\TH:i', datetime: $dateTimeString);

        if ($dateProcessed instanceof DateTime) {
            return $dateProcessed;
        }

        $dateProcessed = DateTime::createFromFormat(format: 'Y-m-d', datetime: $dateTimeString);

        if ($dateProcessed instanceof DateTime) {
            return $dateProcessed;
        }

        return new ApiProblem(status: 400, detail: 'Invalid date format');
    }
}
