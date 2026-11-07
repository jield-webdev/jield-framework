<?php

declare(strict_types=1);

namespace JieldTest\Form\TestAsset;

use Jield\Form\Annotation\ComposedObject;
use Jield\Form\Annotation\Name;

#[Name('contact')]
final class ContactData
{
    #[Name('address')]
    #[ComposedObject(AddressData::class, options: ['label' => 'Address'])]
    public AddressData $address;
}
