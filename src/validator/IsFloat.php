<?php

declare(strict_types=1);

namespace Jield\Validator;

use Laminas\Validator\AbstractValidator;

use function get_debug_type;

final class IsFloat extends AbstractValidator
{
    public const string NOT_FLOAT = 'NotFloat';

    /** @var array<string, string> */
    protected array $messageTemplates = [
        self::NOT_FLOAT => 'Expected an float value but %type% provided',
    ];

    /** @var array<string, string|array<string, string>> */
    protected array $messageVariables = [
        'type' => 'type',
    ];

    protected ?string $type = null;

    public function isValid(mixed $value): bool
    {
        if (is_float($value)) {
            return true;
        }

        $this->type = get_debug_type($value);
        $this->error(self::NOT_FLOAT);

        return false;
    }
}
