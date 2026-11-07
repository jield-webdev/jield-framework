<?php

declare(strict_types=1);

namespace JieldTest\Form\TestAsset;

use Jield\Form\Annotation\ComposedObject;
use Jield\Form\Annotation\Name;

#[Name('address-book')]
final class AddressBookData
{
    #[Name('addresses')]
    #[ComposedObject(AddressData::class, isCollection: true)]
    public array $addresses;
}
