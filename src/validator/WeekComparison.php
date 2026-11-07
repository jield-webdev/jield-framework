<?php

declare(strict_types=1);

namespace Jield\Validator;

use DateTimeImmutable;
use InvalidArgumentException;
use Laminas\Validator\DateComparison;
use Laminas\Validator\ValidatorInterface;

/** Compare HTML week values using their ISO calendar dates. */
final class WeekComparison implements ValidatorInterface
{
    private readonly DateComparison $comparison;

    /** @var array<string, string> */
    private array $messages = [];

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(?string $min = null, ?string $max = null)
    {
        $minimum = $min === null ? null : self::date($min);
        $maximum = $max === null ? null : self::date($max);
        if ($min !== null && $minimum === null || $max !== null && $maximum === null) {
            throw new InvalidArgumentException('Week bounds must use the ISO YYYY-Www format.');
        }
        $this->comparison = new DateComparison(['min' => $minimum, 'max' => $maximum]);
    }

    public function isValid(mixed $value): bool
    {
        $date = is_string($value) ? self::date($value) : null;
        if ($date === null) {
            $this->messages = ['invalidWeek' => 'Expected an ISO week in the format YYYY-Www.'];
            return false;
        }
        $valid = $this->comparison->isValid($date);
        $this->messages = $this->comparison->getMessages();
        return $valid;
    }

    /** @return array<string, string> */
    public function getMessages(): array
    {
        return $this->messages;
    }

    private static function date(string $week): ?DateTimeImmutable
    {
        $matches = [];
        if (preg_match('/^(\d{4})-W(\d{2})$/D', $week, $matches) !== 1) {
            return null;
        }
        $date = new DateTimeImmutable('1970-01-01')->setISODate((int) $matches[1], (int) $matches[2]);
        return $date->format('o-\WW') === $week ? $date : null;
    }
}
