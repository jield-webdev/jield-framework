<?php

declare(strict_types=1);

namespace JieldTest\Form\TestAsset;

use Jield\Form\Annotation\Name;
use Jield\Form\Annotation\Type;
use Jield\Form\Fieldset;

#[Name('profile')]
final class ProfileWithFieldset
{
    #[Name('details')]
    #[Type(Fieldset::class)]
    public array $details;

    #[Name('title')]
    public string $title;
}
