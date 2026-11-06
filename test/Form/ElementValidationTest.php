<?php

declare(strict_types=1);

namespace Jield\Form;

use Laminas\Validator\ValidatorInterface;
use PHPUnit\Framework\TestCase;

final class ElementValidationTest extends TestCase
{
    public function testNumericInputsEnforceInclusiveBoundsAndStep(): void
    {
        $number = new Element\Number('quantity');
        $number->setAttributes(['min' => 1, 'max' => 3, 'step' => 1]);
        foreach (['1', '2', '3'] as $value) {
            self::assertTrue($this->valid($number, $value), $value);
        }
        foreach (['0', '4', '1.5', 'not-a-number'] as $value) {
            self::assertFalse($this->valid($number, $value), $value);
        }
    }

    public function testRangeInputsAcceptNumericFormStrings(): void
    {
        $range = new Element\Range('volume');
        self::assertTrue($this->valid($range, '50'));
        self::assertFalse($this->valid($range, '-1'));
        self::assertFalse($this->valid($range, '101'));
        self::assertFalse($this->valid($range, 'invalid'));
    }

    public function testDateInputsEnforceCalendarBounds(): void
    {
        $date = new Element\Date('day');
        $date->setAttributes(['min' => '2026-01-01', 'max' => '2026-01-31', 'step' => 'any']);
        self::assertTrue($this->valid($date, '2026-01-15'));
        self::assertFalse($this->valid($date, '2025-12-31'));
        self::assertFalse($this->valid($date, '2026-02-01'));
    }

    public function testWeekInputsEnforceWeekBounds(): void
    {
        $week = new Element\Week('week');
        $week->setAttributes(['min' => '2026-W02', 'max' => '2026-W04', 'step' => 'any']);
        self::assertTrue($this->valid($week, '2026-W03'));
        self::assertFalse($this->valid($week, '2026-W01'));
        self::assertFalse($this->valid($week, '2026-W05'));
    }

    private function valid(Element $element, string $value): bool
    {
        self::assertInstanceOf(\Laminas\InputFilter\InputProviderInterface::class, $element);
        $validators = $element->getInputSpecification()['validators'];
        foreach ($validators as $validator) {
            self::assertInstanceOf(ValidatorInterface::class, $validator);
            if (!$validator->isValid($value)) {
                return false;
            }
        }
        return true;
    }
}
