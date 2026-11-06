<?php

declare(strict_types=1);

namespace Jield\Stdlib;

use Laminas\InputFilter\CollectionInputFilter;
use Laminas\InputFilter\Exception\InvalidArgumentException;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\InputFilter\InputFilterValidationResult;
use Laminas\InputFilter\InputInterface;
use Laminas\InputFilter\OptionalInputFilter;

/** Preserve form and PATCH validation groups with the stateless validation API. */
final class InputFilterValidator
{
    /**
     * @param iterable<array-key, mixed> $data
     * @param array<array-key, mixed>|null $group
     * @param array<array-key, mixed> $context
     * @return InputFilterValidationResult<array<array-key, mixed>>
     */
    public static function validate(
        InputFilterInterface $filter,
        iterable $data,
        ?array $group = null,
        array $context = [],
    ): InputFilterValidationResult {
        $data = is_array($data) ? $data : iterator_to_array($data);
        if ($group === null || ($filter instanceof OptionalInputFilter && $data === [])) {
            /** @var InputFilterValidationResult<array<array-key, mixed>> $result */
            $result = $filter->validate($data, $context);
            return $result;
        }

        if ($filter instanceof CollectionInputFilter) {
            $collection = clone $filter;
            $collection->setInputFilter(new CollectionValidationGroups($filter->getInputFilter(), array_values($group)));
            $result = $collection->validate($data, $context);
            return $result;
        }

        $context = $context === [] ? $data : $context;
        $results = [];
        /** @var mixed $selection */
        foreach ($group as $key => $selection) {
            $name = is_array($selection) ? $key : Value::key($selection);
            if (!$filter->has($name)) {
                throw new InvalidArgumentException(sprintf(
                    'Validation group expects a list of valid input names; "%s" was not found',
                    $name,
                ));
            }
            $input = $filter->get($name);
            /** @var mixed $value */
            $value = $data[$name] ?? null;
            if ($input instanceof InputFilterInterface) {
                $results[$name] = self::validate(
                    $input,
                    is_iterable($value) ? Value::array(is_array($value) ? $value : iterator_to_array($value)) : [],
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
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    public static function fieldsIn(array $data, bool $collection = false): array
    {
        if (!$collection) {
            return array_keys($data);
        }
        $groups = [];
        /** @var mixed $item */
        foreach ($data as $item) {
            $groups[] = array_keys(Value::array($item));
        }
        return $groups;
    }
}
