<?php

declare(strict_types=1);

namespace Jield\Export\ValueObject;

use codename\parquet\data\DataColumn;
use codename\parquet\data\DataField;
use codename\parquet\data\DateTimeDataField;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use Jield\Export\Helper\TextHelpers;
use Jield\Stdlib\Value;
use Webmozart\Assert\Assert;

final class Column
{
    public const string TYPE_STRING = 'string';
    public const string TYPE_INTEGER = 'integer';
    public const string TYPE_DATE = 'date';
    public const string TYPE_TIME = 'time';
    public const string TYPE_BOOLEAN = 'boolean';
    public const string TYPE_FLOAT = 'float';

    private array $data = [];

    public array $types = [
        self::TYPE_STRING,
        self::TYPE_INTEGER,
        self::TYPE_DATE,
        self::TYPE_TIME,
        self::TYPE_BOOLEAN,
        self::TYPE_FLOAT,
    ];

    public function __construct(
        private readonly string $columnName,
        private readonly string $type = self::TYPE_STRING,
        private bool $isNullable = true,
        private readonly ?string $description = null,
    ) {
        Assert::inArray(value: $type, values: $this->types);
    }

    public function getColumnName(): string
    {
        return $this->columnName;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isNullableText(): string
    {
        return $this->isNullable ? 'Yes' : '';
    }

    public function addRow(int|string|float|bool|DateTime|DateTimeImmutable|null $data): void
    {
        if (!$this->isNullable && $data === null) {
            throw new InvalidArgumentException(
                message: 'Data cannot be null, column: ' . $this->columnName . ' type: ' . $this->type . ' is null',
            );
        }

        switch ($this->type) {
            case self::TYPE_INTEGER:
            case self::TYPE_BOOLEAN:
                if (is_bool($data)) {
                    //Booleans are also integers, so we need to set the type to integer
                    $this->isNullable = true;

                    $data = $data === true ? 1 : null;
                }

                !$this->isNullable
                    && Assert::integer(
                        value: $data,
                        message: 'Data is not an integer for column ' . $this->columnName . ' but of type: '
                            . gettype($data),
                    );

                break;
            case self::TYPE_STRING:
            case self::TYPE_TIME:
                if ($data instanceof DateTimeInterface) {
                    $data = $data->format(format: 'H:i:s');
                }

                $data = TextHelpers::beautifyTextValue(value: Value::nullableString($data));
                break;
            case self::TYPE_DATE:
                if (null !== $data) {
                    Assert::isInstanceOf(value: $data, class: DateTimeInterface::class);
                    //The parquet converter uses an internal system to derive the amount of unix days, therefore
                    //We have a mismatch when calculating the days from a DateTimeImmutable object because of timezone differences
                    //Therefore we need to convert the DateTimeImmutable object to a DateTimeImmutable object but then in the UTC timezone
                    //This way the parquet converter will calculate the correct amount of days
                    //The parquet converter will then convert the amount of days back to a DateTimeImmutable object
                    //This is a workaround for the parquet converter
                    $dateInLocalTimezone = $data instanceof DateTimeImmutable
                        ? $data
                        : DateTimeImmutable::createFromMutable(object: $data);

                    $data = new DateTimeImmutable()->setTimezone(timezone: new DateTimeZone('UTC'));
                    $data = $data->setDate(
                        year: (int) $dateInLocalTimezone->format(format: 'Y'),
                        month: (int) $dateInLocalTimezone->format(format: 'm'),
                        day: (int) $dateInLocalTimezone->format(format: 'd'),
                    )->setTime(hour: 0, minute: 0, second: 0);
                }
                break;
        }

        $this->data[] = $data;
    }

    public function toParquetColumn(): DataColumn
    {
        $field = match ($this->type) {
            self::TYPE_DATE => DateTimeDataField::create(name: $this->columnName, format: 4),
            self::TYPE_TIME => DataField::createFromType(name: $this->columnName, type: self::TYPE_STRING),
            self::TYPE_BOOLEAN => DataField::createFromType(name: $this->columnName, type: self::TYPE_INTEGER),
            default => DataField::createFromType(name: $this->columnName, type: $this->type),
        };

        return new DataColumn(field: $field, data: $this->data);
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }
}
