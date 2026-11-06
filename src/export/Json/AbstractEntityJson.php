<?php

declare(strict_types=1);

namespace Jield\Export\Json;

use Doctrine\ORM\EntityManager;
use Psr\Container\ContainerInterface;

abstract class AbstractEntityJson
{
    protected string $name = 'entity';
    protected string $provider = 'provider';

    protected string $entity = 'Admin\Entity\Entity';

    protected int $chunkSize = 100_000;

    protected EntityManager $entityManager;
    protected mixed $providerInstance;

    public function __construct(
        protected readonly ContainerInterface $container,
    ) {
        $this->entityManager = \Jield\Stdlib\Service::get($this->container, EntityManager::class);
        $this->providerInstance = $this->container->get($this->provider);
    }

    protected function findCount(array $criteria): int
    {
        return $this->entityManager->getRepository(\Jield\Stdlib\Value::classString($this->entity))->count(criteria: \Jield\Stdlib\Value::stringKeyedArray($criteria));
    }

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

    /**
     * @throws \JsonException
     */
    public function getJsonData(): string
    {
        $i = 0;

        $jsonData = [];

        while ($i < $this->findCount(criteria: [])) {
            $elements = $this->findSliced(offset: $i);

            /** @var mixed $element */
            foreach ($elements as $element) {
                $jsonData[] = \Jield\Stdlib\Value::call($this->providerInstance, 'generateArray', [$element]);
            }

            //clear the entity manager to prevent piling up entities
            $this->entityManager->clear();

            $i += $this->chunkSize;
        }

        return json_encode(value: $jsonData, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Default empty array.
     *
     * @return array<string, string>
     */
    public function getDependencies(): array
    {
        return [];
    }
}
