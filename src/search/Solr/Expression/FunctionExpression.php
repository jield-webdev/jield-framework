<?php

declare(strict_types=1);

namespace Jield\Search\Solr\Expression;

use JetBrains\PhpStorm\Pure;
use Jield\Search\Solr\ExpressionInterface;
use Stringable;

class FunctionExpression extends Expression implements Stringable
{
    /**
     * @param string|ExpressionInterface $function
     * @param array|ExpressionInterface|string|null $parameters
     */
    public function __construct(
        private ExpressionInterface|string $function,
        private ExpressionInterface|array|string|null $parameters = null,
    ) {}

    #[Pure]
    public function __toString(): string
    {
        $parameters = $this->parameters ?: null;

        if ($parameters && !$parameters instanceof ParameterExpression) {
            $parameters = new ParameterExpression(parameters: is_array($parameters) ? $parameters : [$parameters]);
        }

        return (string) $this->function . '(' . (string) $parameters . ')';
    }
}
