<?php

declare(strict_types=1);

namespace DoctrineModule\Validator;

use Doctrine\Persistence\ObjectManager;
use DoctrineModule\Persistence\ObjectManagerAwareInterface;
use DoctrineModule\Persistence\ProvidesObjectManager;
use Jield\Stdlib\Value;
use Laminas\Validator\Exception;
use Override;
use Webmozart\Assert\Assert;

use function array_diff_assoc;
use function array_key_exists;
use function gettype;
use function is_object;
use function sprintf;

/**
 * Class that validates if objects exist in a given repository with a given list of matched fields only once.
 */
class UniqueObject extends ObjectExists implements ObjectManagerAwareInterface
{
    use ProvidesObjectManager;

    /**
     * Error constants
     */
    public const string ERROR_OBJECT_NOT_UNIQUE = 'objectNotUnique';

    /** @var array<string, string> */
    protected array $messageTemplates = [
        self::ERROR_OBJECT_NOT_UNIQUE => "There is already another object matching '%value%'",
    ];

    protected bool $useContext;

    /**
     * @param array $options required keys are `object_manager`
     */
    public function __construct(array $options)
    {
        parent::__construct(options: $options);

        if (($options['object_manager'] ?? null) === null || !$options['object_manager'] instanceof ObjectManager) {
            $provided = match (true) {
                !array_key_exists(key: 'object_manager', array: $options) => 'nothing',
                is_object(value: $options['object_manager']) => $options['object_manager']::class,
                default => gettype(value: $options['object_manager']),
            };

            throw new Exception\InvalidArgumentException(message: sprintf(
                'Option "object_manager" is required and must be an instance of'
                . ' Doctrine\Persistence\ObjectManager, %s given',
                $provided,
            ));
        }

        $this->objectManager = $options['object_manager'];
        $this->useContext = ($options['use_context'] ?? null) !== null ? Value::truthy($options['use_context']) : false;
    }

    /**
     * Returns false if there is another object with the same field values but other identifiers.
     */
    #[Override]
    public function isValid(mixed $value, mixed $context = null): bool
    {
        if (!$this->useContext) {
            $context = Value::toArray($value);
        }

        $cleanedValue = $this->cleanSearchValue(value: $value);
        $match = $this->objectRepository->findOneBy(criteria: Value::stringKeyedArray($cleanedValue));

        if (!is_object(value: $match)) {
            return true;
        }

        $expectedIdentifiers = $this->getExpectedIdentifiers(context: Value::nullableArrayOrObject($context));
        $foundIdentifiers = $this->getFoundIdentifiers(match: $match);

        if (array_diff_assoc($expectedIdentifiers, $foundIdentifiers) === []) {
            return true;
        }

        $this->error(messageKey: self::ERROR_OBJECT_NOT_UNIQUE, value: $value);

        return false;
    }

    /**
     * Gets the identifiers from the matched object.
     *
     *
     * @throws Exception\RuntimeException
     */
    protected function getFoundIdentifiers(object $match): array
    {
        return $this->objectManager->getClassMetadata(className: $this->getClassName())->getIdentifierValues($match);
    }

    /**
     * Gets the identifiers from the context.
     *
     * @param array<array-key, mixed>|object|null $context
     *
     *
     * @throws Exception\RuntimeException
     */
    protected function getExpectedIdentifiers(array|object|null $context = null): array
    {
        if ($context === null) {
            throw new Exception\RuntimeException(message: 'Expected context to be an array but is null');
        }

        $className = $this->objectRepository->getClassName();

        if ($context instanceof $className) {
            return $this->objectManager
                ->getClassMetadata(className: $this->getClassName())
                ->getIdentifierValues($context);
        }

        $result = [];
        /** @mago-var mixed $identifierField */
        foreach ($this->getIdentifiers() as $identifierField) {
            if (!array_key_exists(key: Assert::scalar($identifierField), array: Assert::isArray($context))) {
                throw new Exception\RuntimeException(message: sprintf(
                    'Expected context to contain %s',
                    Value::scalar($identifierField),
                ));
            }

            $result[\Jield\Stdlib\Value::key($identifierField)] = $context[$identifierField];
        }

        return $result;
    }

    /** @return array the names of the identifiers */
    protected function getIdentifiers(): array
    {
        return $this->objectManager->getClassMetadata(className: $this->getClassName())->getIdentifierFieldNames();
    }

    /** @return class-string */
    protected function getClassName(): string
    {
        return $this->objectRepository->getClassName();
    }
}
