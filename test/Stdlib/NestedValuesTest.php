<?php

declare(strict_types=1);

namespace JieldTest\Stdlib;

use ArrayObject;
use Jield\Stdlib\Value;
use PHPUnit\Framework\TestCase;

final class NestedValuesTest extends TestCase
{
    public function testNestedUpdatesPreserveSiblingsAndAppendInOrder(): void
    {
        $values = ['field' => ['label' => 'Name']];
        Value::setPath($values, ['field', 'rules', null], 'required');
        Value::setPath($values, ['field', 'rules', null], 'trim');
        self::assertSame(['field' => ['label' => 'Name', 'rules' => ['required', 'trim']]], $values);
    }

    public function testNestedUpdatesKeepArrayAccessObjects(): void
    {
        $values = new ArrayObject(['field' => new ArrayObject(['label' => 'Name'])]);
        $child = $values['field'];
        Value::setPath($values, ['field', 'required'], true);
        self::assertSame($child, $values['field']);
        self::assertSame(['label' => 'Name', 'required' => true], $child->getArrayCopy());
    }

    public function testCheckedCallsPreserveNamedArguments(): void
    {
        $object = new class {
            public function combine(string $first, string $second): string
            {
                return $first . $second;
            }
        };
        self::assertSame('AB', Value::call($object, 'combine', ['second' => 'B', 'first' => 'A']));
    }

    public function testInternalCallsKeepTheirOriginalVisibilityScope(): void
    {
        $object = new class {
            public function dispatch(): string
            {
                return Value::call($this, 'hidden', ['value' => 'ok'], scope: self::class);
            }

            protected function hidden(string $value): string
            {
                return $value;
            }
        };
        self::assertSame('ok', $object->dispatch());
        $this->expectException(\TypeError::class);
        Value::call($object, 'hidden', ['value' => 'outside']);
    }

    public function testNestedReadsAndArrayCastsPreserveArrayObjectStorage(): void
    {
        $value = new ArrayObject(['nested' => new ArrayObject(['value' => 3])]);
        self::assertSame(3, Value::path($value, ['nested', 'value']));
        self::assertSame($value->getArrayCopy(), Value::toArray($value));
    }
}
