<?php

declare(strict_types=1);

namespace JieldTest\Form\TestAsset;

use Jield\Form\Annotation\Filter;
use Laminas\Filter\StringTrim;

final class ProfileData
{
    #[Filter(StringTrim::class)]
    public string $name;
}
