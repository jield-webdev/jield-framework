<?php

declare(strict_types=1);

namespace DoctrineORMModule\Service;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Types\Type;
use DoctrineORMModule\Options\Configuration as DoctrineORMModuleConfiguration;
use InvalidArgumentException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;
use UnexpectedValueException;
use Webmozart\Assert\Assert;

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
     * @throws RuntimeException
     * @throws \Doctrine\DBAL\Exception
     * @throws \Psr\Container\ContainerExceptionInterface
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

    /**
     * @throws RuntimeException
     * @throws \Doctrine\DBAL\Exception
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws InvalidArgumentException
     */
    public function setupDBALConfiguration(ContainerInterface $container, Configuration $config): void
    {
        $options = $this->getOptions(container: $container);
        $config->setResultCache(Assert::isInstanceOf($container->get($options->getResultCache()), \Psr\Cache\CacheItemPoolInterface::class));

        $middlewares = [];
        foreach ($options->getMiddlewares() as $middlewareName) {
            if (!$container->has($middlewareName)) {
                throw new InvalidArgumentException(message: 'Middleware not exists');
            }

            /** @mago-var mixed $middleware */
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

        /** @mago-var mixed $class */
        foreach (Assert::isArray($options->getTypes()) as $name => $class) {
            if (!$class instanceof Type) {
                Assert::string($class);
                Assert::isAOf($class, Type::class);
            }
            if (Type::hasType(name: Assert::string($name))) {
                Type::overrideType(name: Assert::string($name), type: $class);
            } else {
                Type::addType(name: Assert::string($name), type: $class);
            }
        }
    }

    /**
     * @throws \RuntimeException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function getOptions(ContainerInterface $container): DoctrineORMModuleConfiguration
    {
        /** @mago-var mixed $options */
        $options = $container->get('config');
        /** @mago-var mixed $options */
        $options = \Jield\Stdlib\Value::accessible($options)['doctrine'];
        /** @mago-var mixed $options */
        $options = $options['configuration'][$this->name] ?? null;

        if ($options === null) {
            throw new RuntimeException(message: sprintf(
                'Configuration with name "%s" could not be found in "doctrine.configuration".',
                $this->name,
            ));
        }

        $optionsClass = $this->getOptionsClass();

        return new $optionsClass(\Jield\Stdlib\Value::options($options));
    }

    /** @return class-string<DoctrineORMModuleConfiguration> */
    protected function getOptionsClass(): string
    {
        return DoctrineORMModuleConfiguration::class;
    }
}
