<?php

declare(strict_types=1);

namespace DoctrineModule\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

final class SerializedType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getClobTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return $value === null ? null : serialize($value);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if ($value === null) {
            return null;
        }
        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }
        /** @var mixed $result */
        $result = unserialize(\Jield\Stdlib\Value::string($value));
        if ($result === false && $value !== 'b:0;') {
            throw new ConversionException('Invalid serialized database value.');
        }
        return $result;
    }
}
