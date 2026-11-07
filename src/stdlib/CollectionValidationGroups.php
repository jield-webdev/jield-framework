<?php

declare(strict_types=1);

namespace Jield\Stdlib;

use Jield\Form\InputFilterFactory;
use Laminas\InputFilter\BaseInputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\InputFilter\InputFilterValidationResult;

/**
 * Select the matching group as a collection validates each row.
 * @extends BaseInputFilter<array<array-key, mixed>>
 * @internal
 */
final class CollectionValidationGroups extends BaseInputFilter
{
    private int $position = 0;

    /** @param list<mixed> $groups */
    public function __construct(
        private readonly InputFilterInterface $filter,
        private readonly array $groups,
    ) {
        parent::__construct(InputFilterFactory::create());
    }

    /**
     * @param iterable<array-key, mixed> $data
     * @param array<array-key, mixed> $context
     * @return InputFilterValidationResult<array<array-key, mixed>>
     */
    public function validate(iterable $data, array $context = []): InputFilterValidationResult
    {
        /** @mago-var mixed $group */
        $group = $this->groups[$this->position++] ?? null;
        return InputFilterValidator::validate(
            $this->filter,
            $data,
            match (true) {
                $group === null => null,
                is_array($group) => $group,
                default => [$group],
            },
            $context,
        );
    }
}
