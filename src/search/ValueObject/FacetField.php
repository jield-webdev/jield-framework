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

    // Preserve the existing parameter contract used by callers and inherited interfaces.
    // @mago-expect lint:excessive-parameter-list(1)
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
    ) {
    }

    #[Pure]
    public static function fromArray(array $params): FacetField
    {
        return new self(
            field: \Webmozart\Assert\Assert::string($params['field'] ?? ''),
            name: \Webmozart\Assert\Assert::string($params['name'] ?? ''),
            type: \Webmozart\Assert\Assert::integer($params['name'] ?? self::TYPE_CHECKBOX),
            minCount: \Webmozart\Assert\Assert::integer($params['minCount'] ?? 0),
            sort: \Webmozart\Assert\Assert::string($params['sort'] ?? 'index'),
            reverse: \Webmozart\Assert\Assert::boolean($params['reverse'] ?? false),
            hasYesNo: \Webmozart\Assert\Assert::boolean($params['hasYesNo'] ?? false),
            hasAndOr: \Webmozart\Assert\Assert::boolean($params['hasAndOr'] ?? false),
            defaultValue: \Webmozart\Assert\Assert::nullOrString($params['defaultValue'] ?? false),
            limit: \Webmozart\Assert\Assert::integer($params['limit'] ?? 100),
            visibility: \Webmozart\Assert\Assert::isInstanceOf(
                $params['visibility'] ?? FacetFieldVisibilityEnum::FILTER_COLUMN,
                \Jield\Search\Enum\FacetFieldVisibilityEnum::class,
            ),
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
