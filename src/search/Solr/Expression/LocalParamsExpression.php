<?php

declare(strict_types=1);

namespace Jield\Search\Solr\Expression;

use Jield\Search\Solr\ExpressionInterface;

use Jield\Search\Solr\Util;
use Stringable;

use function count;
use function current;
use function key;

class LocalParamsExpression extends Expression implements Stringable
{
    /**
     * @param string|ExpressionInterface $type
     * @param mixed[] $params
     */
    public function __construct(
        private ExpressionInterface|string $type,
        private readonly array $params = [],
        private readonly bool $shortForm = true,
    ) {}

    public function __toString(): string
    {
        $typeString = $this->shortForm ? (string) $this->type : 'type=' . (string) $this->type;
        $paramsString = $this->buildParamString();

        return '{!' . $typeString . $paramsString . '}';
    }

    private function buildParamString(): string
    {
        if ($this->shortForm && count(value: $this->params) === 1 && key(array: $this->params) === $this->type) {
            return '=' . Util::sanitize(value: current(array: $this->params));
        }

        $paramsString = '';

        /** @var mixed $value */
        foreach ($this->params as $key => $value) {
            $paramsString .= ' ' . $key . '=' . Util::sanitize(value: $value);
        }

        return $paramsString;
    }
}
