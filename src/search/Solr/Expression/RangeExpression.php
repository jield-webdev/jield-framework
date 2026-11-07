<?php

declare(strict_types=1);

namespace Jield\Search\Solr\Expression;

use Jield\Search\Solr\ExpressionInterface;
use Jield\Search\Solr\Util;
use Stringable;

use function sprintf;

/**
 * Range expression class
 *
 * Let you specify range queries in the like of field:[<start> TO <end>] or field:{<start> TO <end>}
 */
class RangeExpression extends Expression implements Stringable
{
    /**
     * Create new range query object
     */
    public function __construct(
        protected ExpressionInterface|float|int|string|null $start = null,
        protected ExpressionInterface|float|int|string|null $end = null,
        /**
         * Inclusive or exclusive the range start/end?
         */
        protected bool $inclusive = true,
    ) {}

    public function __toString(): string
    {
        return sprintf(
            '%s%s TO %s%s',
            $this->inclusive ? '[' : '{',
            $this->cast(value: $this->start),
            $this->cast(value: $this->end),
            $this->inclusive ? ']' : '}',
        );
    }

    private function cast(ExpressionInterface|int|float|string|null $value): string
    {
        return $value === null ? '*' : Util::sanitize(value: $value);
    }
}
