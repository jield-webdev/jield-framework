<?php

declare(strict_types=1);

namespace Jield\Search\Solr\Expression;

use JetBrains\PhpStorm\Pure;
use Jield\Search\Solr\ExpressionInterface;
use Stringable;

class FunctionExpression extends Expression implements Stringable
{
    public function __construct(
        private readonly ExpressionInterface|string $function,
        private readonly ExpressionInterface|array|string|null $parameters = null,
    ) {
    }

    #[Pure]
    public function __toString(): string
    {
        $parameters = $this->parameters ? $this->parameters : null;

        if ($parameters && !$parameters instanceof ParameterExpression) {
            $parameters = new ParameterExpression(parameters: is_array($parameters) ? $parameters : [$parameters]);
        }

        return (string) $this->function . '(' . (string) $parameters . ')';
    }
}
