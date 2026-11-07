<?php

declare(strict_types=1);

namespace DoctrineModule\Service;

use Doctrine\Persistence\Mapping\Driver\DefaultFileLocator;
use Doctrine\Persistence\Mapping\Driver\FileDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use DoctrineModule\Options\Driver;
use InvalidArgumentException;
use Jield\Stdlib\Value;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Webmozart\Assert\Assert;

use function class_exists;
use function sprintf;
use function str_contains;

/**
 * MappingDriver ServiceManager factory
 */
final class DriverFactory extends AbstractFactory
{
    /**
     * {@inheritDoc}
     *
     * @return MappingDriver
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): MappingDriver
    {
        $driverOptions = $this->getOptions(container: $container, key: 'driver');

        if (!$driverOptions instanceof Driver) {
            throw new RuntimeException(message: sprintf(
                'Invalid options received, expected %s, got %s.',
                Driver::class,
                $driverOptions::class,
            ));
        }

        return $this->createDriver(container: $container, options: $driverOptions);
    }

    public function getOptionsClass(): string
    {
        return Driver::class;
    }

    /** @throws InvalidArgumentException */
    protected function createDriver(ContainerInterface $container, Driver $options): MappingDriver
    {
        $class = $options->getClass();

        if (str_contains($class, 'AnnotationDriver')) {
            throw new InvalidArgumentException(
                message: 'Annotation drivers are no longer supported. Use attributes, XML, YAML, or PHP drivers instead.',
            );
        }

        if (!class_exists(class: $class)) {
            throw new InvalidArgumentException(message: sprintf('Driver with type "%s" could not be found', $class));
        }

        // Not all drivers (DriverChain) require paths.
        $paths = $options->getPaths();

        $driver = Value::instantiate($class, [$paths]);

        if ($options->getExtension() && $driver instanceof FileDriver) {
            $locator = $driver->getLocator();

            if ($locator::class !== DefaultFileLocator::class) {
                throw new InvalidArgumentException(message: sprintf(
                    'Discovered file locator for driver of type "%s" is an instance of "%s". This factory '
                    . 'supports only the DefaultFileLocator when an extension is set for the file locator',
                    $driver::class,
                    $locator::class,
                ));
            }

            $driver->setLocator(locator: new DefaultFileLocator(
                paths: $locator->getPaths(),
                fileExtension: $options->getExtension(),
            ));
        }

        // Extra post-create options for DriverChain.
        if ($driver instanceof MappingDriverChain && $options->getDrivers() !== []) {
            $drivers = $options->getDrivers();

            /** @mago-var mixed $driverName */
            foreach ($drivers as $namespace => $driverName) {
                if ($driverName === null) {
                    continue;
                }

                $options = $this->getOptions(container: $container, key: 'driver', name: Assert::nullOrString($driverName));

                if (!$options instanceof Driver) {
                    throw new RuntimeException(message: sprintf(
                        'Invalid options received, expected %s, got %s.',
                        Driver::class,
                        $options::class,
                    ));
                }

                $driver->addDriver(
                    nestedDriver: $this->createDriver(container: $container, options: $options),
                    namespace: Assert::string($namespace),
                );
            }
        }

        return Assert::isInstanceOf($driver, \Doctrine\Persistence\Mapping\Driver\MappingDriver::class);
    }
}
