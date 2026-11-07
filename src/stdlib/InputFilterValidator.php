<?php

declare(strict_types=1);

namespace Jield\Stdlib;

use Laminas\InputFilter\CollectionInputFilter;
use Laminas\InputFilter\Exception\InvalidArgumentException;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\InputFilter\InputFilterValidationResult;
use Laminas\InputFilter\OptionalInputFilter;

/** Preserve form and PATCH validation groups with the stateless validation API. */
final class InputFilterValidator
{
    /**
     * @param iterable<array-key, mixed> $data
     * @param array<array-key, mixed>|null $group
     * @param array<array-key, mixed> $context
     * @return InputFilterValidationResult<array<array-key, mixed>>
     * @throws InvalidArgumentException
     */
    public static function validate(
        InputFilterInterface $filter,
        iterable $data,
        ?array $group = null,
        array $context = [],
    ): InputFilterValidationResult {
        $data = is_array($data) ? $data : iterator_to_array($data);
        if ($group === null || $filter instanceof OptionalInputFilter && $data === []) {
            $result = $filter->validate($data, $context);
            return self::preserveCollectionKeys($filter, $data, $result);
        }

        if ($filter instanceof CollectionInputFilter) {
            $collection = clone $filter;
            $collection->setInputFilter(
                new CollectionValidationGroups($filter->getInputFilter(), array_values($group)),
            );
            $result = $collection->validate($data, $context);
            return self::preserveCollectionKeys($filter, $data, $result);
        }

        $context = $context === [] ? $data : $context;
        $results = [];
        /** @mago-var mixed $selection */
        foreach ($group as $key => $selection) {
            /** @mago-var mixed $name */
            $name = is_array($selection) ? $key : $selection;
            if (!$filter->has(\Jield\Stdlib\Value::key($name))) {
                throw new InvalidArgumentException(sprintf(
                    'Validation group expects a list of valid input names; "%s" was not found',
                    \Jield\Stdlib\Value::scalar($name),
                ));
            }
            $input = $filter->get(\Jield\Stdlib\Value::key($name));
            /** @mago-var mixed $value */
            $value = $data[$name] ?? null;
            if ($input instanceof InputFilterInterface) {
                $results[$name] = self::validate(
                    $input,
                    is_iterable($value) ? (is_array($value) ? $value : iterator_to_array($value)) : [],
                    is_array($selection) ? $selection : null,
                    $context,
                );
                continue;
            }
            $result = $input->validate($value, $context);
            $results[$name] = $result;
            if (!$result->valid() && $input->breakOnFailure()) {
                break;
            }
        }

        /** @var InputFilterValidationResult<array<array-key, mixed>> */
        return new InputFilterValidationResult($results);
    }

    /**
     * InputFilter 3 validates collection rows as a list. Form fieldsets use the submitted
     * keys to identify existing objects, so keep those keys in values and messages.
     *
     * @template TFilteredValues
     * @param array<array-key, mixed> $data
     * @param InputFilterValidationResult<TFilteredValues> $result
     * @return InputFilterValidationResult<array<array-key, mixed>>
     */
    private static function preserveCollectionKeys(
        InputFilterInterface $filter,
        array $data,
        InputFilterValidationResult $result,
    ): InputFilterValidationResult {
        $results = [];
        $keys = array_keys($data);
        foreach ($result->results as $position => $child) {
            $key = $position;
            $input = $filter->has($key) ? $filter->get($key) : null;
            if ($filter instanceof CollectionInputFilter) {
                $key = $keys[$position] ?? $position;
                // Keep missing rows from count validation without overwriting a submitted row.
                while (array_key_exists($key, $results)) {
                    $key = is_int($key) ? $key + 1 : count($results);
                }
                $input = $filter->getInputFilter();
            }
            if ($input instanceof InputFilterInterface && $child instanceof InputFilterValidationResult) {
                /** @mago-var mixed $value */
                $value = $data[$key] ?? [];
                $child = self::preserveCollectionKeys(
                    $input,
                    is_iterable($value) ? (is_array($value) ? $value : iterator_to_array($value)) : [],
                    $child,
                );
            }
            $results[$key] = $child;
        }
        return new InputFilterValidationResult($results);
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    public static function fieldsIn(array $data, bool $collection = false): array
    {
        if (!$collection) {
            return array_keys($data);
        }
        $groups = [];
        /** @mago-var mixed $item */
        foreach ($data as $item) {
            $groups[] = array_keys(\Webmozart\Assert\Assert::isArray($item));
        }
        return $groups;
    }
}
