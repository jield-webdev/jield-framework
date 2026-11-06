<?php

declare(strict_types=1);

namespace Jield\Search\Solr\Expression;

use Jield\Search\Solr\ExpressionInterface;

use JetBrains\PhpStorm\Pure;
use Jield\Search\Solr\Util;
use Stringable;

/**
 * Field query expression
 *
 * Class representing a query limited to specific fields (field:<value>)
 */
class FieldExpression extends Expression implements Stringable
{
    /**
     * Create new field query
     *
     * @param string|ExpressionInterface $field
     * @param string|ExpressionInterface $expr
     */
    #[Pure]
    public function __construct(
        private ExpressionInterface|string $field,
        ExpressionInterface|string $expr,
    ) {
        parent::__construct(expr: $expr);
    }

    public function __toString(): string
    {
        $field = Util::escape(value: $this->field);
        $expression = Util::sanitize(value: $this->expr);

        if ($this->expr instanceof LocalParamsExpression) {
            return $expression . $field;
        }

        return $field . ':' . $expression;
    }
}
