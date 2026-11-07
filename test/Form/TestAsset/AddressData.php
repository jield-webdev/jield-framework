<?php

declare(strict_types=1);

namespace JieldTest\Form\TestAsset;

use Jield\Form\Annotation\Filter;
use Jield\Form\Annotation\Name;
use Jield\Form\Annotation\Required;
use Laminas\Filter\StringTrim;

#[Name('address')]
final class AddressData
{
    #[Name('street')]
    #[Required]
    #[Filter(StringTrim::class)]
    public string $street;
}
