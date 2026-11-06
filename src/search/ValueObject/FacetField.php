<?php

declare(strict_types=1);

namespace Jield\Search\ValueObject;

use JetBrains\PhpStorm\Pure;
use Jield\Search\Enum\FacetFieldVisibilityEnum;

final class FacetField
{
    public const int TYPE_CHECKBOX = 1;
    public const int TYPE_SLIDER = 2;
    public const int TYPE_CHECKBOX_MIN = 3;

    public function __construct(
        private readonly string $field,
        private readonly string $name,
        private readonly int $type = self::TYPE_CHECKBOX,
        private readonly int $minCount = 1,
        private string $sort = 'index', //or count
        private readonly bool $reverse = false,
        private readonly bool $hasYesNo = false,
        private readonly bool $hasAndOr = false,
        private readonly ?string $defaultValue = null,
        private readonly int $limit = 100,
        private readonly FacetFieldVisibilityEnum $visibility = FacetFieldVisibilityEnum::FILTER_COLUMN,
    ) {}

    #[Pure]
    public static function fromArray(array $params): FacetField
    {
        return new self(
            field: \Jield\Stdlib\Value::string($params['field'] ?? ''),
            name: \Jield\Stdlib\Value::string($params['name'] ?? ''),
            type: \Jield\Stdlib\Value::int($params['name'] ?? self::TYPE_CHECKBOX),
            minCount: \Jield\Stdlib\Value::int($params['minCount'] ?? 0),
            sort: \Jield\Stdlib\Value::string($params['sort'] ?? 'index'),
            reverse: \Jield\Stdlib\Value::bool($params['reverse'] ?? false),
            hasYesNo: \Jield\Stdlib\Value::bool($params['hasYesNo'] ?? false),
            hasAndOr: \Jield\Stdlib\Value::bool($params['hasAndOr'] ?? false),
            defaultValue: \Jield\Stdlib\Value::nullableString($params['defaultValue'] ?? false),
            limit: \Jield\Stdlib\Value::int($params['limit'] ?? 100),
            visibility: \Jield\Stdlib\Value::instance($params['visibility'] ?? FacetFieldVisibilityEnum::FILTER_COLUMN, \Jield\Search\Enum\FacetFieldVisibilityEnum::class),
        );
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isSlider(): bool
    {
        return $this->type === self::TYPE_SLIDER;
    }

    public function isCheckboxMin(): bool
    {
        return $this->type === self::TYPE_CHECKBOX_MIN;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function getReverse(): bool
    {
        return $this->reverse;
    }

    public function getSort(): string
    {
        return $this->sort;
    }

    public function setSort(string $sort): FacetField
    {
        $this->sort = $sort;
        return $this;
    }

    public function getHasYesNo(): bool
    {
        return $this->hasYesNo;
    }

    public function getHasAndOr(): bool
    {
        return $this->hasAndOr;
    }

    public function getMinCount(): int
    {
        return $this->minCount;
    }

    public function getDefaultValue(): ?string
    {
        return $this->defaultValue;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getVisibility(): FacetFieldVisibilityEnum
    {
        return $this->visibility;
    }
}
