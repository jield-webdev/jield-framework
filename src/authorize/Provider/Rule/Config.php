<?php

declare(strict_types=1);

namespace Jield\Authorize\Provider\Rule;

/**
 * Rule provider based on a given array of rules
 */
class Config implements ProviderInterface
{
    /** @var array */
    protected array $rules = [];

    /**
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->rules = $config;
    }

    /**
     * {@inheritDoc}
     */
    public function getRules(): array
    {
        return $this->rules;
    }
}
