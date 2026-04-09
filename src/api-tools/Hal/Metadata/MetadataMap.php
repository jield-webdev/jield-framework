<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Metadata;

use Jield\ApiTools\Hal\Exception;
use Laminas\Hydrator\HydratorPluginManager;
use Laminas\Hydrator\HydratorPluginManagerInterface;
use Laminas\ServiceManager\ServiceManager;
use function array_key_exists;
use function get_debug_type;
use function get_parent_class;
use function is_array;
use function is_object;
use function sprintf;

class MetadataMap
{
    protected null|HydratorPluginManagerInterface|HydratorPluginManager $hydrators;

    /** @var array<class-string, array<string,string>|Metadata> */
    protected array $map = [];

    /**
     * Constructor
     *
     * If provided, will pass $map to setMap().
     * If provided, will pass $hydrators to setHydratorManager().
     */
    public function __construct(?array $map = null, ?HydratorPluginManagerInterface $hydrators = null)
    {
        if ($hydrators instanceof \Laminas\Hydrator\HydratorPluginManagerInterface) {
            $this->setHydratorManager(hydrators: $hydrators);
        }

        if ($map !== null && $map !== []) {
            $this->setMap(map: $map);
        }
    }

    public function setHydratorManager(HydratorPluginManagerInterface $hydrators): static
    {
        $this->hydrators = $hydrators;

        return $this;
    }

    public function getHydratorManager(): HydratorPluginManager|HydratorPluginManagerInterface
    {
        if (null === $this->hydrators) {
            $hydrators = new HydratorPluginManager(configInstanceOrParentLocator: new ServiceManager());
            $this->setHydratorManager(hydrators: $hydrators);
            return $hydrators;
        }

        return $this->hydrators;
    }

    /**
     * Set the metadata map
     *
     * Accepts an array of class => metadata definitions.
     * Each definition may be an instance of Metadata, or an array
     * of options used to define a Metadata instance.
     *
     * @param array<class-string, array<string,string>|Metadata> $map
     * @return static
     * @throws Exception\InvalidArgumentException
     */
    public function setMap(array $map): static
    {
        foreach ($map as $class => $options) {
            $metadata = $options;
            if (!is_array(value: $metadata) && !$metadata instanceof Metadata) {
                throw new Exception\InvalidArgumentException(message: sprintf(
                    '%s expects each map to be an array or a Jield\ApiTools\Hal\Metadata instance; received "%s"',
                    __METHOD__,
                    get_debug_type(value: $metadata)
                ));
            }

            $this->map[$class] = $metadata;
        }

        return $this;
    }

    /**
     * Does the map contain metadata for the given class?
     *
     * @psalm-param  object|class-string $class Object or class name to test
     */
    public function has($class): bool
    {
        $className = is_object(value: $class) ? $class::class : $class;

        if (array_key_exists(key: $className, array: $this->map)) {
            return true;
        }

        if (get_parent_class(object_or_class: $className)) {
            return $this->has(class: get_parent_class(object_or_class: $className));
        }

        return false;
    }

    /**
     * Retrieve the metadata for a given class
     *
     * Lazy-loads the Metadata instance if one is not present for a matching class.
     *
     * @psalm-param object|class-string $class Object or classname for which to retrieve metadata
     */
    public function get($class): Metadata|false
    {
        $className = is_object(value: $class) ? $class::class : $class;

        if (isset($this->map[$className])) {
            return $this->getMetadataInstance(class: $className);
        }

        if (get_parent_class(object_or_class: $className)) {
            return $this->get(class: get_parent_class(object_or_class: $className));
        }

        return false;
    }

    /**
     * Retrieve a metadata instance.
     *
     * @psalm-param class-string $class
     */
    private function getMetadataInstance($class): Metadata
    {
        if ($this->map[$class] instanceof Metadata) {
            return $this->map[$class];
        }

        $this->map[$class] = new Metadata(class: $class, options: $this->map[$class], hydrators: $this->getHydratorManager());
        return $this->map[$class];
    }
}
