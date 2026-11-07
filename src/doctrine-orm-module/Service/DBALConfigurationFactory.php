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
        $config->setResultCache($container->get($options->getResultCache()));

        if (method_exists(object_or_class: $config, method: 'setMiddlewares')) {
            $middlewares = [];
            foreach ($options->getMiddlewares() as $middlewareName) {
                if (!$container->has($middlewareName)) {
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

        foreach (Assert::isArray($options->getTypes()) as $name => $class) {
            if (!$class instanceof Type) {
                Assert::string($class);
                Assert::isAOf($class, Type::class);
            }
            if (Type::hasType(name: $name)) {
                Type::overrideType(name: $name, type: $class);
            } else {
                Type::addType(name: $name, type: $class);
            }
        }
    }

    /** @throws \RuntimeException */
    public function getOptions(ContainerInterface $container): DoctrineORMModuleConfiguration
    {
        $options = $container->get('config');
        $options = $options['doctrine'];
        $options = $options['configuration'][$this->name] ?? null;

        if ($options === null) {
            throw new RuntimeException(message: sprintf(
                'Configuration with name "%s" could not be found in "doctrine.configuration".',
                $this->name,
            ));
        }

        $optionsClass = $this->getOptionsClass();

        return new $optionsClass($options);
    }

    /** @return class-string<DoctrineORMModuleConfiguration> */
    protected function getOptionsClass(): string
    {
        return DoctrineORMModuleConfiguration::class;
    }
}
