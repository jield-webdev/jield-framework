<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal;

use Jield\ApiTools\Hal\Metadata\MetadataMap;
use Laminas\Hydrator\ExtractionInterface;
use Laminas\Hydrator\HydratorPluginManager;
use Laminas\Hydrator\HydratorPluginManagerInterface;
use function is_string;
use function strtolower;

class EntityHydratorManager
{
    /** @var HydratorPluginManager|HydratorPluginManagerInterface */
    protected HydratorPluginManagerInterface|HydratorPluginManager $hydrators;

    /** @var MetadataMap */
    protected MetadataMap $metadataMap;

    /**
     * Map of class name/(hydrator instance|name) pairs
     *
     * @var array
     */
    protected array $hydratorMap = [];

    /**
     * Default hydrator to use if no hydrator found for a specific entity class.
     *
     * @var ExtractionInterface
     */
    protected ExtractionInterface $defaultHydrator;

    /**
     * @param HydratorPluginManager|HydratorPluginManagerInterface $hydrators
     * @throws Exception\InvalidArgumentException If $hydrators is of invalid type.
     */
    public function __construct(HydratorPluginManager|HydratorPluginManagerInterface $hydrators, MetadataMap $map)
    {
        $this->hydrators   = $hydrators;
        $this->metadataMap = $map;
    }

    /**
     * @return HydratorPluginManager|HydratorPluginManagerInterface
     */
    public function getHydratorManager(): HydratorPluginManager|HydratorPluginManagerInterface
    {
        return $this->hydrators;
    }

    /**
     * Map an entity class to a specific hydrator instance
     *
     * @param string $class
     * @param string|ExtractionInterface $hydrator
     * @return static
     */
    public function addHydrator(string $class, ExtractionInterface|string $hydrator): static
    {
        if (is_string(value: $hydrator)) {
            /** @var ExtractionInterface $hydratorInstance */
            $hydratorInstance = $this->hydrators->get($hydrator);
            $hydrator         = $hydratorInstance;
        }

        $filteredClass                     = strtolower(string: $class);
        $this->hydratorMap[$filteredClass] = $hydrator;
        return $this;
    }

    /**
     * Set the default hydrator to use if none specified for a class.
     *
     */
    public function setDefaultHydrator(ExtractionInterface $hydrator): static
    {
        $this->defaultHydrator = $hydrator;
        return $this;
    }

    /**
     * Retrieve a hydrator for a given entity
     *
     * If the entity has a mapped hydrator, returns that hydrator. If not, and
     * a default hydrator is present, the default hydrator is returned.
     * Otherwise, a boolean false is returned.
     *
     * @param object $entity
     * @return ExtractionInterface|false
     */
    public function getHydratorForEntity(object $entity): false|ExtractionInterface
    {
        $class      = $entity::class;
        $classLower = strtolower(string: $class);

        if (isset($this->hydratorMap[$classLower])) {
            return $this->hydratorMap[$classLower];
        }

        if ($this->metadataMap->has(class: $entity)) {
            $metadata = $this->metadataMap->get(class: $class);
            /** @psalm-suppress PossiblyFalseReference */
            $hydrator = $metadata->getHydrator();
            if ($hydrator instanceof ExtractionInterface) {
                $this->addHydrator(class: $class, hydrator: $hydrator);
                return $hydrator;
            }
        }

        if ($this->defaultHydrator instanceof ExtractionInterface) {
            return $this->defaultHydrator;
        }

        return false;
    }
}
