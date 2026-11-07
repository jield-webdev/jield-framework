<?php

declare(strict_types=1);

namespace DoctrineModule\Service;

use Jield\Stdlib\Value;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\Stdlib\AbstractOptions;
use Psr\Container\ContainerInterface;
use RuntimeException;

use function sprintf;

/**
 * Base ServiceManager factory
 *
 * @interal
 */
abstract class AbstractFactory implements FactoryInterface
{
    /**
     * Would normally be set to orm | odm
     */
    protected ?string $mappingType = null;

    protected ?AbstractOptions $options = null;

    public function __construct(
        protected string $name,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Would normally be set to orm | odm
     */
    public function getMappingType(): string
    {
        return (string) $this->mappingType;
    }

    /**
     * Gets options from configuration based on name.
     *
     * @throws \RuntimeException
     */
    public function getOptions(ContainerInterface $container, string $key, ?string $name = null): AbstractOptions
    {
        $name ??= $this->getName();

        $options = $container->get('config');
        /** @var mixed $options */
        $options = Value::accessible($options)['doctrine'];

        $mappingType = $this->getMappingType();
        if ($mappingType !== '' && $mappingType !== '0') {
            /** @var mixed $options */
            $options = Value::accessible($options)[$mappingType];
        }

        /** @var mixed $options */
        $options = $options[$key][$name] ?? null;

        if ($options === null) {
            throw new RuntimeException(message: sprintf(
                'Options with name "%s" could not be found in "doctrine.%s".',
                $name,
                $key,
            ));
        }

        $optionsClass = $this->getOptionsClass();

        return Value::instance(
            Value::instantiate($optionsClass, [$options]),
            \Laminas\Stdlib\AbstractOptions::class,
        );
    }

    /**
     * Get the class name of the options associated with this factory.
     *
     * @abstract
     */
    abstract public function getOptionsClass(): string;
}
