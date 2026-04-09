<?php

declare(strict_types=1);

namespace Jield\ApiTools\Provider;

interface ProviderInterface
{
    public function generateArray($entity): array;
}