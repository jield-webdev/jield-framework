<?php

declare(strict_types=1);

namespace Jield\Authorize\Provider\Rule;

/**
 * Rule provider based on a given array of rules
 */
class Config implements ProviderInterface
{
    public function __construct(
        protected array $rules = [],
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function getRules(): array
    {
        return $this->rules;
    }
}
