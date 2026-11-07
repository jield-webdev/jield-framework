<?php

declare(strict_types=1);

namespace DoctrineORMModule\Service;

use Doctrine\ORM\Cache\CacheConfiguration;
use Doctrine\ORM\Cache\DefaultCacheFactory;
use Doctrine\ORM\Cache\RegionsConfiguration;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Internal\Hydration\AbstractHydrator;
use Doctrine\ORM\Mapping\EntityListenerResolver;
use Doctrine\ORM\Mapping\NamingStrategy;
use Doctrine\ORM\Mapping\QuoteStrategy;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Doctrine\ORM\Repository\RepositoryFactory;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use DoctrineORMModule\Options\Configuration as DoctrineORMModuleConfiguration;
use DoctrineORMModule\Service\DBALConfigurationFactory as DoctrineConfigurationFactory;
use Jield\Stdlib\Value;
use Laminas\ServiceManager\Exception\InvalidArgumentException;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;

use function is_string;
use function method_exists;
use function sprintf;

final class ConfigurationFactory extends DoctrineConfigurationFactory
{
    /** @return array<string, class-string<FunctionNode>|callable(string): FunctionNode> */
    private function dqlFunctions(array $functions): array
    {
        $result = [];
        foreach (Value::stringKeyedArray($functions) as $name => $function) {
            if (is_string($function) && !is_callable($function)) {
                $result[$name] = Value::subclass($function, FunctionNode::class);
            } else {
                $callback = Value::callback($function);
                $result[$name] = static fn(string $name): FunctionNode => Value::instance($callback($name), FunctionNode::class);
            }
        }
        return $result;
    }

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
        $options = $this->getOptions(container: $container);
        $config = new Configuration();
        $config->enableNativeLazyObjects(true);

        $config->setAutoGenerateProxyClasses(autoGenerate: $options->getGenerateProxies());
        $config->setProxyDir(dir: Value::string($options->getProxyDir()));
        $config->setProxyNamespace(ns: Value::string($options->getProxyNamespace()));

        $config->setEntityNamespaces(entityNamespaces: Value::stringMap($options->getEntityNamespaces()));

        $config->setCustomDatetimeFunctions(functions: $this->dqlFunctions($options->getDatetimeFunctions()));
        $config->setCustomStringFunctions(functions: $this->dqlFunctions($options->getStringFunctions()));
        foreach ($this->dqlFunctions($options->getNumericFunctions()) as $name => $function) {
            $config->addCustomNumericFunction($name, $function);
        }

        if ($options->getClassMetadataFactoryName() !== null) {
            $config->setClassMetadataFactoryName(
                Value::classString($options->getClassMetadataFactoryName()),
            );
        }

        if ($options->getNamedQueries() !== [] || $options->getNamedNativeQueries() !== []) {
            throw new ServiceNotCreatedException(
                'Doctrine ORM no longer supports named queries; configure queries in repositories instead.',
            );
        }

        /** @var mixed $modeName
         * @var mixed $hydrator
         */
        foreach ($options->getCustomHydrationModes() as $modeName => $hydrator) {
            $config->addCustomHydrationMode(
                modeName: Value::string($modeName),
                hydrator: Value::subclass(
                    $hydrator,
                    AbstractHydrator::class,
                ),
            );
        }

        /** @var mixed $name
         * @var mixed $class
         */
        foreach ($options->getFilters() as $name => $class) {
            $config->addFilter(
                name: Value::string($name),
                className: Value::subclass($class, SQLFilter::class),
            );
        }

        $config->setMetadataCache(Value::instance(
            $container->get(Value::string($options->getMetadataCache())),
            CacheItemPoolInterface::class,
        ));
        $config->setQueryCache(Value::instance(
            $container->get(Value::string($options->getQueryCache())),
            CacheItemPoolInterface::class,
        ));
        $config->setResultCache(Value::instance(
            $container->get(Value::string($options->getResultCache())),
            CacheItemPoolInterface::class,
        ));
        $config->setHydrationCache(Value::instance(
            $container->get(Value::string($options->getHydrationCache())),
            CacheItemPoolInterface::class,
        ));
        $config->setMetadataDriverImpl(driverImpl: Value::instance(
            $container->get(Value::string($options->getDriver())),
            MappingDriver::class,
        ));

        /** @var mixed $namingStrategy */
        $namingStrategy = $options->getNamingStrategy();
        if ($namingStrategy) {
            if (is_string(value: $namingStrategy)) {
                if (!$container->has($namingStrategy)) {
                    throw new InvalidArgumentException(message: sprintf(
                        'Naming strategy "%s" not found',
                        $namingStrategy,
                    ));
                }

                $config->setNamingStrategy(namingStrategy: Value::instance(
                    $container->get($namingStrategy),
                    NamingStrategy::class,
                ));
            } else {
                $config->setNamingStrategy(namingStrategy: Value::instance(
                    $namingStrategy,
                    NamingStrategy::class,
                ));
            }
        }

        /** @var mixed $quoteStrategy */
        $quoteStrategy = $options->getQuoteStrategy();
        if ($quoteStrategy) {
            if (is_string(value: $quoteStrategy)) {
                if (!$container->has($quoteStrategy)) {
                    throw new InvalidArgumentException(message: sprintf(
                        'Quote strategy "%s" not found',
                        $quoteStrategy,
                    ));
                }

                $config->setQuoteStrategy(quoteStrategy: Value::instance(
                    $container->get($quoteStrategy),
                    QuoteStrategy::class,
                ));
            } else {
                $config->setQuoteStrategy(quoteStrategy: Value::instance(
                    $quoteStrategy,
                    QuoteStrategy::class,
                ));
            }
        }

        /** @var mixed $repositoryFactory */
        $repositoryFactory = $options->getRepositoryFactory();
        if ($repositoryFactory) {
            if (is_string(value: $repositoryFactory)) {
                if (!$container->has($repositoryFactory)) {
                    throw new InvalidArgumentException(message: sprintf(
                        'Repository factory "%s" not found',
                        $repositoryFactory,
                    ));
                }

                $config->setRepositoryFactory(repositoryFactory: Value::instance(
                    $container->get($repositoryFactory),
                    RepositoryFactory::class,
                ));
            } else {
                $config->setRepositoryFactory(repositoryFactory: Value::instance(
                    $repositoryFactory,
                    RepositoryFactory::class,
                ));
            }
        }

        /** @var mixed $entityListenerResolver */
        $entityListenerResolver = $options->getEntityListenerResolver();
        if ($entityListenerResolver) {
            if ($entityListenerResolver instanceof EntityListenerResolver) {
                $config->setEntityListenerResolver(resolver: $entityListenerResolver);
            } else {
                $config->setEntityListenerResolver(resolver: Value::instance(
                    $container->get(Value::string($entityListenerResolver)),
                    \Doctrine\ORM\Mapping\EntityListenerResolver::class,
                ));
            }
        }

        /** @var mixed $secondLevelCache */
        $secondLevelCache = $options->getSecondLevelCache();

        if (Value::call($secondLevelCache, 'isEnabled')) {
            $regionsConfig = new RegionsConfiguration(
                defaultLifetime: Value::int(Value::call(
                    $secondLevelCache,
                    'getDefaultLifetime',
                )),
                defaultLockLifetime: Value::int(Value::call(
                    $secondLevelCache,
                    'getDefaultLockLifetime',
                )),
            );

            /** @var mixed $regionName
             * @var mixed $regionConfig
             */
            foreach (Value::iterable(Value::call(
                $secondLevelCache,
                'getRegions',
            )) as $regionName => $regionConfig) {
                if (isset($regionConfig['lifetime'])) {
                    $regionsConfig->setLifetime(
                        name: Value::string($regionName),
                        lifetime: Value::int($regionConfig['lifetime']),
                    );
                }

                if (!isset($regionConfig['lock_lifetime'])) {
                    continue;
                }

                $regionsConfig->setLockLifetime(
                    name: Value::string($regionName),
                    lifetime: Value::int($regionConfig['lock_lifetime']),
                );
            }

            // As Second Level Cache caches queries results, we reuse the result cache impl
            $cacheFactory =
                new DefaultCacheFactory(regionsConfig: $regionsConfig, cacheItemPool: Value::instance(
                    $config->getResultCache(),
                    CacheItemPoolInterface::class,
                ));
            $cacheFactory->setFileLockRegionDirectory(fileLockRegionDirectory: Value::string(Value::call(
                $secondLevelCache,
                'getFileLockRegionDirectory',
            )));

            $cacheConfiguration = new CacheConfiguration();
            $cacheConfiguration->setCacheFactory(factory: $cacheFactory);
            $cacheConfiguration->setRegionsConfiguration(regionsConfig: $regionsConfig);

            $config->setSecondLevelCacheEnabled();
            $config->setSecondLevelCacheConfiguration(cacheConfig: $cacheConfiguration);
        }

        // only works for DBAL 2.x, not for 3.x
        if (method_exists(object_or_class: $config, method: 'setFilterSchemaAssetsExpression')) {
            /** @var mixed $filterSchemaAssetsExpression */
            $filterSchemaAssetsExpression = $options->getFilterSchemaAssetsExpression();
            if ($filterSchemaAssetsExpression) {
                $config->setFilterSchemaAssetsExpression($filterSchemaAssetsExpression);
            }
        }

        // DBAL 2.x
        if (method_exists(object_or_class: $config, method: 'setSchemaAssetsFilter')) {
            /** @var mixed $schemaAssetsFilter */
            $schemaAssetsFilter = $options->getSchemaAssetsFilter();
            if ($schemaAssetsFilter) {
                $config->setSchemaAssetsFilter(schemaAssetsFilter: Value::callback($schemaAssetsFilter));
            }
        }

        /** @var mixed $className */
        $className = $options->getDefaultRepositoryClassName();
        if ($className) {
            $config->setDefaultRepositoryClassName(className: Value::subclass(
                $className,
                EntityRepository::class,
            ));
        }

        $this->setupDBALConfiguration(container: $container, config: $config);

        return $config;
    }

    /** @return class-string<DoctrineORMModuleConfiguration> */
    protected function getOptionsClass(): string
    {
        return DoctrineORMModuleConfiguration::class;
    }
}
