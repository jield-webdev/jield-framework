<?php

declare(strict_types=1);

namespace DoctrineORMModule\Service;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Types\Type;
use DoctrineORMModule\Options\Configuration as DoctrineORMModuleConfiguration;
use InvalidArgumentException;
use Jield\Stdlib\Value;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;
use UnexpectedValueException;

use function method_exists;
use function sprintf;

/**
 * DBAL Configuration ServiceManager factory
 */
class DBALConfigurationFactory implements FactoryInterface
{
    public function __construct(
        protected string $name,
    ) {}

    /**
     * {@inheritDoc}
     *
     * @param string $requestedName
     *
     * @return Configuration
     */
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): Configuration {
        $config = new Configuration();
        $this->setupDBALConfiguration(container: $container, config: $config);

        return $config;
    }

    public function setupDBALConfiguration(ContainerInterface $container, Configuration $config): void
    {
        $options = $this->getOptions(container: $container);
        $config->setResultCache(Value::instance(
            $container->get(Value::string($options->getResultCache())),
            CacheItemPoolInterface::class,
        ));

        if (method_exists(object_or_class: $config, method: 'setMiddlewares')) {
            $middlewares = [];
            /** @var mixed $middlewareName */
            foreach ($options->getMiddlewares() as $middlewareName) {
                if (!is_string(value: $middlewareName) || !$container->has($middlewareName)) {
                    throw new InvalidArgumentException(message: 'Middleware not exists');
                }

                $middleware = $container->get($middlewareName);
                if (!$middleware instanceof Middleware) {
                    throw new UnexpectedValueException(message: sprintf(
                        'Invalid middleware with %s name. %s expected.',
                        $middlewareName,
                        Middleware::class,
                    ));
                }

                $middlewares[] = $middleware;
            }

            $config->setMiddlewares(middlewares: $middlewares);
        }

        /** @var mixed $name
         * @var mixed $class
         */
        foreach (Value::array($options->getTypes()) as $name => $class) {
            $class = $class instanceof Type ? $class : Value::subclass($class, Type::class);
            if (Type::hasType(name: Value::string($name))) {
                Type::overrideType(name: Value::string($name), type: $class);
            } else {
                Type::addType(name: Value::string($name), type: $class);
            }
        }
    }

    /** @throws \RuntimeException */
    public function getOptions(ContainerInterface $container): DoctrineORMModuleConfiguration
    {
        $options = $container->get('config');
        /** @var mixed $options */
        $options = Value::accessible($options)['doctrine'];
        /** @var mixed $options */
        $options = $options['configuration'][$this->name] ?? null;

        if ($options === null) {
            throw new RuntimeException(message: sprintf(
                'Configuration with name "%s" could not be found in "doctrine.configuration".',
                $this->name,
            ));
        }

        $optionsClass = $this->getOptionsClass();

        return new $optionsClass(Value::stringKeyedArray($options));
    }

    /** @return class-string<DoctrineORMModuleConfiguration> */
    protected function getOptionsClass(): string
    {
        return DoctrineORMModuleConfiguration::class;
    }
}
