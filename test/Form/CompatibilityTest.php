<?php

declare(strict_types=1);

namespace JieldTest\Form;

use ArrayIterator;
use Jield\Form\Element;
use Jield\Form\Factory;
use Jield\Form\Fieldset;
use Jield\Form\View\Helper\FormSelect;
use Laminas\Hydrator\ObjectPropertyHydrator;
use PHPUnit\Framework\TestCase;

final class CompatibilityTest extends TestCase
{
    public function testCollectionExtractsItsArrayOfObjects(): void
    {
        $collection = new Element\Collection('items');
        $collection->setHydrator(new ObjectPropertyHydrator());
        $collection->setObject(new ArrayIterator([(object) ['name' => 'first'], (object) ['name' => 'second']]));

        self::assertSame([['name' => 'first'], ['name' => 'second']], $collection->extract());
    }

    public function testCloningFieldsetsPreservesNumericElementNames(): void
    {
        $fieldset = new Fieldset('items');
        $fieldset->add(new Element('0'));
        $fieldset->add(new Element('name'));
        $clone = clone $fieldset;

        self::assertTrue($clone->has('0'));
        self::assertNotSame($fieldset->get('0'), $clone->get('0'));
        self::assertTrue($clone->has('name'));
    }

    public function testFactoryAcceptsTraversableAttributesAndElements(): void
    {
        $fieldset = new Factory()->createFieldset([
            'name' => 'profile',
            'attributes' => new ArrayIterator(['id' => 'profile']),
            'hydrator' => new ObjectPropertyHydrator(),
            'elements' => new ArrayIterator([
                ['spec' => ['name' => 'name', 'type' => Element::class]],
            ]),
        ]);

        self::assertSame('profile', $fieldset->getAttribute('id'));
        self::assertTrue($fieldset->has('name'));
        self::assertInstanceOf(ObjectPropertyHydrator::class, $fieldset->getHydrator());
    }

    public function testSelectGroupsEscapeLabelsAndSelectNumericValues(): void
    {
        $element = new Element\Select('choice');
        $element->setValueOptions([
            [
                'label' => 'Group <one>',
                'attributes' => ['class' => 'group'],
                'options' => [
                    ['label' => 'First <item>', 'value' => 1],
                    ['label' => 'Second item', 'value' => 2],
                ],
            ],
        ]);
        $element->setValue('2');
        $html = new FormSelect()->render($element);

        self::assertStringContainsString('Group&#x20;&lt;one&gt;', $html);
        self::assertStringContainsString('First &lt;item&gt;', $html);
        self::assertStringContainsString('value="2" selected', $html);
    }
}
