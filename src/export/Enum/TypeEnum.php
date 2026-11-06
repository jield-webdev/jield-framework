<?php

declare(strict_types=1);

namespace Jield\Export\Enum;

enum TypeEnum: string
{
    case IMPORT = 'import';
    case EXPORT = 'export';

    public function toString(): string
    {
        return match ($this) {
            self::IMPORT => 'Import',
            self::EXPORT => 'Export',
        };
    }

    public function isImport(): bool
    {
        return $this === self::IMPORT;
    }

    public function isExport(): bool
    {
        return $this === self::EXPORT;
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
