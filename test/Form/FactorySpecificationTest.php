<?php

declare(strict_types=1);

namespace JieldTest\Form;

use ArrayIterator;
use Jield\Form\Exception\DomainException;
use Jield\Form\Factory;
use Jield\Form\InputFilterFactory;
use JieldTest\Form\TestAsset\InheritedInputFilter;
use JieldTest\Form\TestAsset\OptionalConstructorInputFilter;
use Laminas\Filter\StringTrim;
use Laminas\InputFilter\CollectionInputFilter;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\OptionalInputFilter;
use PHPUnit\Framework\TestCase;
use stdClass;

final class FactorySpecificationTest extends TestCase
{
    public function testInputFilterInstancesArePreserved(): void
    {
        $filter = InputFilterFactory::create()->createInputFilter([
            ['name' => 'name', 'filters' => [['name' => StringTrim::class]]],
        ]);
        $form   = new Factory()->createForm(['input_filter' => $filter]);

        self::assertSame($filter, $form->getInputFilter());
        $form->setData(['name' => ' Alice ']);
        self::assertTrue($form->isValid());
        self::assertSame(['name' => 'Alice'], $form->getData());
    }

    public function testTraversableInputFilterSpecificationsNormalizeInputNames(): void
    {
        $form = new Factory()->createForm([
            'input_filter' => new ArrayIterator([
                'name' => ['filters' => [['name' => StringTrim::class]]],
            ]),
        ]);
        $form->setData(['name' => ' Alice ']);

        self::assertTrue($form->isValid());
        self::assertSame(['name' => 'Alice'], $form->getData());
    }

    public function testNamedInputFiltersReceiveTheirFactory(): void
    {
        $classes = [
            InputFilter::class,
            OptionalInputFilter::class,
            CollectionInputFilter::class,
            InheritedInputFilter::class,
            OptionalConstructorInputFilter::class,
        ];
        foreach ($classes as $class) {
            $form = new Factory()->createForm(['input_filter' => $class]);

            self::assertInstanceOf($class, $form->getInputFilter());
        }
    }

    public function testUnrelatedInputFilterClassesAreRejected(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('valid implementation of Laminas\\InputFilter\\InputFilterInterface');

        new Factory()->createForm(['input_filter' => stdClass::class]);
    }
}
