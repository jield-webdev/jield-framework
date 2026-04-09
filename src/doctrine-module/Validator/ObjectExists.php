<?php

declare(strict_types=1);

namespace DoctrineModule\Validator;

use Doctrine\Persistence\ObjectRepository;
use Jield\Stdlib\ArrayUtils;
use Laminas\Validator\AbstractValidator;
use Laminas\Validator\Exception;
use ValueError;
use function array_combine;
use function array_key_exists;
use function array_values;
use function count;
use function gettype;
use function is_countable;
use function is_object;
use function is_string;
use function sprintf;

/**
 * Class that validates if objects exist in a given repository with a given list of matched fields
 */
class ObjectExists extends AbstractValidator
{
    /**
     * Error constants
     */
    public const string ERROR_NO_OBJECT_FOUND = 'noObjectFound';

    /** @var array Message templates */
    protected array $messageTemplates = [self::ERROR_NO_OBJECT_FOUND => "No object matching '%value%' was found"];

    /**
     * ObjectRepository from which to search for entities
     */
    protected ObjectRepository $objectRepository;

    /**
     * Fields to be checked
     */
    protected mixed $fields = null;

    /**
     * Constructor
     *
     * @param array $options required keys are `object_repository`, which must be an instance of
     *                       Doctrine\Persistence\ObjectRepository, and `fields`, with either
     *                       a string or an array of strings representing the fields to be matched by the validator.
     *
     * @throws Exception\InvalidArgumentException
     */
    public function __construct(array $options)
    {
        if (!isset($options['object_repository']) || !$options['object_repository'] instanceof ObjectRepository) {
            if (!array_key_exists(key: 'object_repository', array: $options)) {
                $provided = 'nothing';
            } elseif (is_object(value: $options['object_repository'])) {
                $provided = $options['object_repository']::class;
            } else {
                $provided = gettype(value: $options['object_repository']);
            }

            throw new Exception\InvalidArgumentException(
                message: sprintf(
                    'Option "object_repository" is required and must be an instance of'
                    . ' Doctrine\Persistence\ObjectRepository, %s given',
                    $provided,
                ),
            );
        }

        $this->objectRepository = $options['object_repository'];

        if (!isset($options['fields'])) {
            throw new Exception\InvalidArgumentException(
                message: 'Key `fields` must be provided and be a field or a list of fields to be used when searching for'
                . ' existing instances',
            );
        }

        $this->fields = $options['fields'];
        $this->validateFields();

        parent::__construct(options: $options);
    }

    /**
     * Filters and validates the fields passed to the constructor
     *
     * @return array
     *
     * @throws Exception\InvalidArgumentException
     */
    private function validateFields(): array
    {
        $fields = (array)$this->fields;

        if ($fields === []) {
            throw new Exception\InvalidArgumentException(message: 'Provided fields list was empty!');
        }

        foreach ($fields as $key => $field) {
            if (!is_string(value: $field)) {
                throw new Exception\InvalidArgumentException(
                    message: sprintf('Provided fields must be strings, %s provided for key %s', gettype(value: $field), $key),
                );
            }
        }

        $this->fields = array_values(array: $fields);

        return $this->fields;
    }

    /**
     * @param string|int|object|array $value a field value or an array of field values if more fields have been configured to be
     *                      matched
     *
     * @return array
     *
     * @throws Exception\RuntimeException
     */
    protected function cleanSearchValue(string|int|object|array $value): array
    {
        $value = is_object(value: $value) ? [$value] : (array)$value;

        if (ArrayUtils::isHashTable(value: $value)) {
            $matchedFieldsValues = [];

            foreach ($this->fields as $field) {
                if (!array_key_exists(key: $field, array: $value)) {
                    throw new Exception\RuntimeException(
                        message: sprintf(
                            'Field "%s" was not provided, but was expected since the configured field lists needs'
                            . ' it for validation',
                            $field,
                        ),
                    );
                }

                $matchedFieldsValues[$field] = $value[$field];
            }
        } else {
            try {
                $matchedFieldsValues = @array_combine(keys: $this->fields, values: $value);
            } catch (ValueError) {
                $matchedFieldsValues = false;
            }

            if ($matchedFieldsValues === false) {
                throw new Exception\RuntimeException(
                    message: sprintf(
                        'Provided values count is %s, while expected number of fields to be matched is %s',
                        count(value: $value),
                        is_countable(value: $this->fields) ? count(value: $this->fields) : 0,
                    ),
                );
            }
        }

        return $matchedFieldsValues;
    }

    /**
     * {@inheritDoc}
     */
    public function isValid($value): bool
    {
        $cleanedValue = $this->cleanSearchValue(value: $value);
        $match        = $this->objectRepository->findOneBy(criteria: $cleanedValue);

        if (is_object(value: $match)) {
            return true;
        }

        $this->error(messageKey: self::ERROR_NO_OBJECT_FOUND, value: $value);

        return false;
    }
}
