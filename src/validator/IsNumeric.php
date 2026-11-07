<?php

declare(strict_types=1);

namespace Jield\Validator;

use Laminas\Validator\AbstractValidator;

final class IsNumeric extends AbstractValidator
{
    public const string NOT_NUMERIC = 'notNumeric';

    /** @var array<string, string> */
    protected array $messageTemplates = [
        self::NOT_NUMERIC => 'The input must be a number',
    ];

    #[\Override]
    public function isValid(mixed $value): bool
    {
        if (is_numeric($value)) {
            return true;
        }

        $this->error(self::NOT_NUMERIC);
        return false;
    }
}
