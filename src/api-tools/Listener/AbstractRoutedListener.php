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

    protected static int $pageSize    = 30;
    protected static int $maxPageSize = 1000;

    protected static array $entityCollectionWhitelist = [];

    public static function getRoute(): string
    {
        return static::$route;
    }

    public static function getPrivilege(): null|string
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

    public static function getRouteAssertionClass(): null|string
    {
        return null;
    }

    protected function convertJSDateTimeToPHPDateTime(?string $dateTimeString): null|DateTime|ApiProblem
    {
        if (empty($dateTimeString)) {
            return null;
        }

        $dateProcessed = DateTime::createFromFormat(format: DateTimeInterface::ATOM, datetime: $dateTimeString);

        if ($dateProcessed instanceof DateTime) {
            return $dateProcessed;
        }

        $dateProcessed = DateTime::createFromFormat(format: DateTimeInterface::RFC3339_EXTENDED, datetime: $dateTimeString);

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

        return new ApiProblem(
            status: 400,
            detail: 'Invalid date format'
        );
    }
}