<?php

declare(strict_types=1);

namespace Jield\Form;

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

    private function filter(): InputFilterInterface
    {
        return InputFilterFactory::create()->createInputFilter(InputFilterFactory::normalizeSpecification([
            'name' => ['required' => true, 'filters' => [['name' => StringTrim::class]]],
            'email' => ['required' => true, 'filters' => [['name' => StringTrim::class]]],
        ]));
    }
}
