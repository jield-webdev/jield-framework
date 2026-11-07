<?php

declare(strict_types=1);

namespace JieldTest\Form;

use Jield\Form\Element;
use Jield\Form\Form;
use Jield\Form\FormInterface;
use Jield\Form\InputFilterFactory;
use Jield\Stdlib\InputFilterValidator;
use Laminas\Filter\StringTrim;
use Laminas\InputFilter\CollectionInputFilter;
use Laminas\InputFilter\Exception\InvalidArgumentException;
use Laminas\InputFilter\InputFilterInterface;
use PHPUnit\Framework\TestCase;

final class ValidationGroupsTest extends TestCase
{
    public function testSelectedFieldsAreFilteredWithoutRequiringOtherFields(): void
    {
        $filter = $this->filter();
        $result = InputFilterValidator::validate($filter, ['name' => ' Alice '], ['name']);
        self::assertTrue($result->valid());
        self::assertSame(['name' => 'Alice'], $result->value());
        self::assertSame(['name' => ' Alice '], $result->rawValue());
        self::assertFalse($filter->validate(['name' => 'Alice'])->valid());
    }

    public function testCollectionRowsCanSelectDifferentFields(): void
    {
        $collection = new CollectionInputFilter(InputFilterFactory::create());
        $collection->setInputFilter($this->filter());
        $collection->setCount(2);
        $data = [['name' => ' Alice '], ['email' => ' alice@example.com ']];
        $result = InputFilterValidator::validate($collection, $data, [['name'], ['email']]);
        self::assertTrue($result->valid());
        self::assertSame([['name' => 'Alice'], ['email' => 'alice@example.com']], $result->value());
        self::assertFalse(InputFilterValidator::validate($collection, [$data[0]], [['name']])->valid());
    }

    public function testUnknownSelectedFieldsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"unknown" was not found');
        InputFilterValidator::validate($this->filter(), ['unknown' => 'value'], ['unknown']);
    }

    public function testFormRetainsItsValidationGroupAndRawData(): void
    {
        $form = new Form('profile');
        $form->add(new Element('name'));
        $form->add(new Element('email'));
        $form->setInputFilter($this->filter());
        $form->setPreferFormInputFilter(true);
        $form->setValidationGroup(['name']);
        $form->setData(['name' => ' Alice ']);
        self::assertTrue($form->isValid());
        self::assertSame(['name' => 'Alice'], $form->getData());
        self::assertSame(['name' => ' Alice '], $form->getData(FormInterface::VALUES_RAW));
    }

    public function testNestedCollectionResultsKeepSubmittedKeysInValuesAndMessages(): void
    {
        $collection = new CollectionInputFilter(InputFilterFactory::create());
        $collection->setInputFilter($this->filter());
        $filter = new \Laminas\InputFilter\InputFilter(InputFilterFactory::create());
        $filter->add($collection, 'rows');
        $data = ['rows' => [5 => ['name' => ' Alice ', 'email' => ' a@example.com '], 19 => ['name' => ' Bob ']]];
        $result = InputFilterValidator::validate($filter, $data);

        self::assertFalse($result->valid());
        self::assertSame([5, 19], array_keys($result->value()['rows']));
        self::assertSame('Alice', $result->value()['rows'][5]['name']);
        self::assertSame(' Alice ', $result->rawValue()['rows'][5]['name']);
        self::assertArrayHasKey(19, $result->getMessages()->toArray()['rows']);
    }

    public function testCollectionValidationGroupsKeepSparseRowNames(): void
    {
        $collection = new CollectionInputFilter(InputFilterFactory::create());
        $collection->setInputFilter($this->filter());
        $result = InputFilterValidator::validate(
            $collection,
            [5 => ['name' => ' Alice '], 19 => ['email' => ' a@example.com ']],
            [['name'], ['email']],
        );

        self::assertTrue($result->valid());
        self::assertSame([5 => ['name' => 'Alice'], 19 => ['email' => 'a@example.com']], $result->value());
    }

    public function testMissingCollectionRowsDoNotOverwriteSubmittedResults(): void
    {
        $collection = new CollectionInputFilter(InputFilterFactory::create());
        $collection->setInputFilter($this->filter());
        $collection->setCount(2);
        $result = InputFilterValidator::validate($collection, [1 => ['name' => ' Alice ', 'email' => 'a@example.com']]);

        self::assertFalse($result->valid());
        self::assertSame([1, 2], array_keys($result->value()));
        self::assertSame('Alice', $result->value()[1]['name']);
    }

    private function filter(): InputFilterInterface
    {
        return InputFilterFactory::create()->createInputFilter(InputFilterFactory::normalizeSpecification([
            'name' => ['required' => true, 'filters' => [['name' => StringTrim::class]]],
            'email' => ['required' => true, 'filters' => [['name' => StringTrim::class]]],
        ]));
    }
}
