<?php

declare(strict_types=1);

namespace Jield\Export\Enum;

enum ExportFileTypeEnum: string
{
    case EXCEL = 'excel';
    case CSV = 'csv';
    case PARQUET = 'parquet';
    case JSON = 'json';

    public function toString(): string
    {
        return match ($this) {
            self::EXCEL => 'Excel',
            self::CSV => 'CSV',
            self::PARQUET => 'Parquet',
            self::JSON => 'JSON',
        };
    }

    public function parseExtension(): string
    {
        return match ($this) {
            self::EXCEL => 'xlsx',
            self::CSV => 'csv',
            self::PARQUET => 'parquet',
            self::JSON => 'json',
        };
    }

    public function getColumnKey(): string
    {
        return match ($this) {
            self::EXCEL, self::CSV, self::PARQUET => 'columns',
            self::JSON => 'json',
        };
    }

    public function isSpreadsheet(): bool
    {
        return in_array(
            $this,
            [
                self::EXCEL,
                self::CSV,
            ],
            strict: true,
        );
    }

    public function hasColumns(): bool
    {
        return in_array(
            $this,
            [
                self::EXCEL,
                self::CSV,
                self::PARQUET,
            ],
            strict: true,
        );
    }

    public function isParquet(): bool
    {
        return $this === self::PARQUET;
    }

    public function isJson(): bool
    {
        return $this === self::JSON;
    }

    public static function toArray(): array
    {
        $results = [];

        foreach (static::cases() as $case) {
            $results[$case->value] = $case->toString();
        }

        asort(array: $results);

        return $results;
    }
}
