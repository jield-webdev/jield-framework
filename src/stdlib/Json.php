<?php

declare(strict_types=1);

namespace Jield\Stdlib;

final class Json
{
    public const int TYPE_OBJECT = 0;
    public const int TYPE_ARRAY = 1;

    public static function encode(mixed $valueToEncode, bool $cycleCheck = false, array $options = []): string
    {
        return json_encode($valueToEncode, JSON_THROW_ON_ERROR);
    }

    public static function decode(string $encodedValue, int $objectDecodeType = self::TYPE_OBJECT): mixed
    {
        return json_decode($encodedValue, $objectDecodeType === self::TYPE_ARRAY, flags: JSON_THROW_ON_ERROR);
    }
}
