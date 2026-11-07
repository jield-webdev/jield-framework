<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Extractor;

use ArrayObject;
use Jield\ApiTools\Hal\EntityHydratorManager;
use JsonSerializable;
use Laminas\Hydrator\ExtractionInterface;
use Override;
use SplObjectStorage;

use function get_object_vars;

/**
 * Extract entities.
 */
class EntityExtractor implements ExtractionInterface
{
    /**
     * Map of entities to their Jield\ApiTools\Hal\Entity serializations
     *
     * @var SplObjectStorage<object, array<array-key, mixed>>
     */
    protected SplObjectStorage $serializedEntities;

    public function __construct(
        protected EntityHydratorManager $entityHydratorManager,
    ) {
        /** @var SplObjectStorage<object, array<array-key, mixed>> $serializedEntities */
        $serializedEntities = new SplObjectStorage();
        $this->serializedEntities = $serializedEntities;
    }

    /**
     * @inheritDoc
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \ReflectionException
     */
    #[Override]
    public function extract(object $object): array
    {
        if ($this->serializedEntities->offsetExists($object)) {
            return $this->serializedEntities->offsetGet($object);
        }

        $this->serializedEntities[$object] = $this->extractEntity(entity: $object);

        return $this->serializedEntities->offsetGet($object);
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \ReflectionException
     */
    private function extractEntity(object $entity): array
    {
        $hydrator = $this->entityHydratorManager->getHydratorForEntity(entity: $entity);

        if ($hydrator) {
            return $hydrator->extract(object: $entity);
        }

        if ($entity instanceof JsonSerializable) {
            return \Webmozart\Assert\Assert::isArray($entity->jsonSerialize());
        }

        if ($entity instanceof ArrayObject) {
            return $entity->getArrayCopy();
        }

        return get_object_vars(object: $entity);
    }
}
