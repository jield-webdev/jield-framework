<?php

declare(strict_types=1);

namespace Jield\Stdlib\ArrayUtils;

final class MergeReplaceKey implements MergeReplaceKeyInterface
{
    public function __construct(protected mixed $data)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getData(): mixed
    {
        return $this->data;
    }
}
