<?php

declare(strict_types=1);

namespace Jield\Form;

use Jield\Stdlib\Value;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

use function array_pop;
use function explode;
use function strtolower;

/**
 * Factory for instantiating form elements
 */
final class ElementFactory implements FactoryInterface
{
    /**
     * Create an instance of the requested class name.
     *
     * @throws \ReflectionException
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): object
    {
        $options ??= [];

        if (isset($options['name'])) {
            /** @mago-var mixed $name */
            $name = $options['name'];
        } else {
            // 'Jield\Form\Element' -> 'element'
            $parts = explode(separator: '\\', string: $requestedName);
            $name = strtolower(string: array_pop(array: $parts));
        }

        if (isset($options['options'])) {
            /** @mago-var mixed $options */
            $options = $options['options'];
        }

        return Value::instantiate($requestedName, [$name, $options]);
    }
}
