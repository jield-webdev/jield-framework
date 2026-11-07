<?php

declare(strict_types=1);

namespace JieldTest\Form;

use Jield\Form\Annotation\AttributeBuilder;
use Jield\Form\Element\Collection;
use Jield\Form\Fieldset;
use JieldTest\Form\TestAsset\AddressBookData;
use JieldTest\Form\TestAsset\ContactData;
use JieldTest\Form\TestAsset\ProfileData;
use JieldTest\Form\TestAsset\ProfileWithFieldset;
use JieldTest\Form\TestAsset\ProfileWithFieldsetAlias;
use PHPUnit\Framework\TestCase;

final class AttributeBuilderTest extends TestCase
{
    public function testClassAndPropertyNamesFallBackToReflection(): void
    {
        $form = new AttributeBuilder()->createForm(ProfileData::class);
        $form->setData(['name' => ' Alice ']);

        self::assertSame('ProfileData', $form->getName());
        self::assertTrue($form->has('name'));
        self::assertTrue($form->isValid());
        self::assertSame(['name' => 'Alice'], $form->getData());
    }

    public function testFieldsetSpecificationsRespectTheirTypeAndDefinedOrder(): void
    {
        $builder = new AttributeBuilder();
        $spec = $builder->getFormSpecification(ProfileWithFieldset::class);

        self::assertCount(1, $spec['fieldsets']);
        self::assertSame('details', $spec['fieldsets'][0]['spec']['name']);
        self::assertSame('title', $spec['elements'][0]['spec']['name']);

        $builder->setPreserveDefinedOrder(true);
        $spec = $builder->getFormSpecification(ProfileWithFieldset::class);

        self::assertSame([], $spec['fieldsets']);
        self::assertSame(
            ['details', 'title'],
            array_map(static fn ($element) => $element['spec']['name'], $spec['elements']),
        );
    }

    public function testComposedObjectsRetainTheirNestedFiltersAndOptions(): void
    {
        $form = new AttributeBuilder()->createForm(ContactData::class);
        $form->setData(['address' => ['street' => ' Main Street ']]);

        self::assertInstanceOf(Fieldset::class, $form->get('address'));
        self::assertSame('Address', $form->get('address')->getOption('label'));
        self::assertTrue($form->isValid());
        self::assertSame(['address' => ['street' => 'Main Street']], $form->getData());

        $form->setData(['address' => ['street' => '']]);
        self::assertFalse($form->isValid());
        self::assertArrayHasKey('street', $form->getMessages()['address']);
    }

    public function testFieldsetAliasesAreRecognizedInSpecifications(): void
    {
        $spec = new AttributeBuilder()->getFormSpecification(ProfileWithFieldsetAlias::class);

        self::assertCount(1, $spec['fieldsets']);
        self::assertSame('details', $spec['fieldsets'][0]['spec']['name']);
    }

    public function testComposedCollectionsRetainTheirNestedFilters(): void
    {
        $form = new AttributeBuilder()->createForm(AddressBookData::class);
        $form->setData(['addresses' => [4 => ['street' => ' Main Street ']]]);

        self::assertInstanceOf(Collection::class, $form->get('addresses'));
        self::assertTrue($form->isValid());
        self::assertSame(['addresses' => [4 => ['street' => 'Main Street']]], $form->getData());
    }
}
