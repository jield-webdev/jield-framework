<?php

declare(strict_types=1);

namespace JieldTest\Form\TestAsset;

use Jield\Form\Annotation\Type;

final class ProfileWithFieldsetAlias
{
    #[Type('fieldset')]
    public array $details;
}
