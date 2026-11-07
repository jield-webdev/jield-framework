<?php

declare(strict_types=1);

namespace Jield\Export\Columns;

use Doctrine\ORM\EntityManager;
use Jield\Export\ValueObject\Column;

abstract class AbstractEntityColumns implements ColumnsHelperInterface
{
    protected string $name = 'dim_entity';

    protected string $entity = 'Admin\Entity\Entity';

    protected int $chunkSize = 100_000;

    protected ?string $description = null;

    public function __construct(
        protected readonly EntityManager $entityManager,
    ) {}

    /** @param array<string, mixed> $criteria Field names and values to match. */
    protected function findCount(array $criteria): int
    {
        return $this->entityManager->getRepository(\Jield\Stdlib\Value::classString($this->entity))->count(criteria: \Jield\Stdlib\Value::stringKeyedArray($criteria));
    }

    /**
     * @param array<string, mixed> $criteria Field names and values to match.
     * @return list<object>
     * @throws \UnexpectedValueException
     */
    protected function findSliced(int $offset, array $criteria = []): array
    {
        return $this->entityManager->getRepository(\Jield\Stdlib\Value::classString($this->entity))->findBy(
            criteria: \Jield\Stdlib\Value::stringKeyedArray($criteria),
            orderBy: [],
            limit: $this->chunkSize,
            offset: $offset,
        );
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return array<Column>
     */
    abstract public function getColumns(): array;

    /**
     * Default empty array.
     *
     * @return array<array-key, string> Names of exports that must run before this export.
     */
    public function getDependencies(): array
    {
        return [];
    }
}
