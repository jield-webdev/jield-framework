<?php

declare(strict_types=1);

namespace Jield\Validator;

use Laminas\Validator\ValidatorInterface;

/** Validate each selected form value without splitting strings on delimiters. */
final class Each implements ValidatorInterface
{
    /** @var array<string, string> */
    private array $messages = [];

    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {}

    public function isValid(mixed $value): bool
    {
        $this->messages = [];
        $valid = true;
        /** @mago-var mixed $item */
        foreach (is_array($value) ? $value : [$value] as $item) {
            if ($this->validator->isValid($item)) {
                continue;
            }
            $valid = false;
            $this->messages = array_replace($this->messages, $this->validator->getMessages());
        }
        return $valid;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }
}
