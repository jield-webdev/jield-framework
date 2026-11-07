<?php

declare(strict_types=1);

namespace DoctrineModule\Validator;

use Override;

use function is_object;

/**
 * Class that validates if objects does not exist in a given repository with a given list of matched fields
 */
class NoObjectExists extends ObjectExists
{
    /**
     * Error constants
     */
    public const string ERROR_OBJECT_FOUND = 'objectFound';

    /** @var array<string, string> Message templates */
    protected array $messageTemplates = [self::ERROR_OBJECT_FOUND => "An object matching '%value%' was found"];

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function isValid($value): bool
    {
        $cleanedValue = $this->cleanSearchValue(value: $value);
        $match = $this->objectRepository->findOneBy(criteria: $cleanedValue);

        if (is_object(value: $match)) {
            $this->error(messageKey: self::ERROR_OBJECT_FOUND, value: $value);

            return false;
        }

        return true;
    }
}
