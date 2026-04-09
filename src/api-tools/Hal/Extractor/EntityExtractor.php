<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Extractor;

use ArrayObject;
use JsonSerializable;
use Jield\ApiTools\Hal\EntityHydratorManager;
use Laminas\Hydrator\ExtractionInterface;
use Override;
use SplObjectStorage;

use function get_object_vars;

/**
 * Extract entities.
 */
class EntityExtractor implements ExtractionInterface
{
    /** @var EntityHydratorManager */
    protected EntityHydratorManager $entityHydratorManager;

    /**
     * Map of entities to their Jield\ApiTools\Hal\Entity serializations
     *
     * @var SplObjectStorage
     */
    protected SplObjectStorage $serializedEntities;

    public function __construct(EntityHydratorManager $entityHydratorManager)
    {
        $this->entityHydratorManager = $entityHydratorManager;
        $this->serializedEntities    = new SplObjectStorage();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function extract(object $object): array
    {
        if (isset($this->serializedEntities[$object])) {
            /** @psalm-var array<array-key, mixed> */
            return $this->serializedEntities[$object];
        }

        $this->serializedEntities[$object] = $this->extractEntity(entity: $object);

        /** @psalm-var array<array-key, mixed> */
        return $this->serializedEntities[$object];
    }

    private function extractEntity(object $entity): array
    {
        $hydrator = $this->entityHydratorManager->getHydratorForEntity(entity: $entity);

        if ($hydrator) {
            return $hydrator->extract(object: $entity);
        }

        if ($entity instanceof JsonSerializable) {
            /** @psalm-var array<array-key, mixed> */
            return $entity->jsonSerialize();
        }

        if ($entity instanceof ArrayObject) {
            return $entity->getArrayCopy();
        }

        return get_object_vars(object: $entity);
    }
}
