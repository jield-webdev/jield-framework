<?php

declare(strict_types=1);

namespace Jield\Form;

use Laminas\InputFilter\Factory;
use Laminas\ServiceManager\ServiceManager;
use Psr\Container\ContainerInterface;

/** @psalm-import-type ServiceManagerConfiguration from \Laminas\ServiceManager\ServiceManager */
final class InputFilterFactory
{
    public static function create(?ContainerInterface $container = null): Factory
    {
        if ($container === null) {
            $dependencies = array_replace_recursive(
                \Jield\Stdlib\Value::stringKeyedArray(new \Laminas\Filter\ConfigProvider()->getDependencyConfig()),
                \Jield\Stdlib\Value::stringKeyedArray(new \Laminas\Validator\ConfigProvider()->getDependencyConfig()),
                \Jield\Stdlib\Value::stringKeyedArray(new \Laminas\InputFilter\ConfigProvider()->getDependencyConfig()),
            );
            \Jield\Stdlib\Value::setPath($dependencies, ['services', 'config', 'validators'], new \DoctrineModule\ConfigProvider()->getValidatorConfig());
            /** @var ServiceManagerConfiguration $managerConfiguration */
        $managerConfiguration = $dependencies;
        $container = new ServiceManager($managerConfiguration);
        }
        return Factory::new($container);
    }

    public static function normalizeSpecification(array $specification): array
    {
        /** @var mixed $input */
        foreach ($specification as $name => &$input) {
            if (
                !(is_string($name) && is_array($input) && !in_array($name, ['options', 'validators', 'filters'], true))
            ) {
                continue;
            }

            $input['name'] ??= $name;
        }
        return $specification;
    }
}
