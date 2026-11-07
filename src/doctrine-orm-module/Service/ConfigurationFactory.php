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
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Filter\SQLFilter;
use DoctrineORMModule\Options\Configuration as DoctrineORMModuleConfiguration;
use DoctrineORMModule\Service\DBALConfigurationFactory as DoctrineConfigurationFactory;
use Jield\Stdlib\Value;
use Laminas\ServiceManager\Exception\InvalidArgumentException;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

use function is_string;
use function sprintf;

final class ConfigurationFactory extends DoctrineConfigurationFactory
{
    /** @return array<string, class-string<FunctionNode>|callable(string): FunctionNode> */
    private function dqlFunctions(array $functions): array
    {
        $result = [];
        /** @mago-var mixed $function */
        foreach ($functions as $name => $function) {
            $name = Assert::string($name);
            if (is_string($function) && !is_callable($function)) {
                Assert::isAOf($function, FunctionNode::class);
                $result[$name] = $function;
            } else {
                $callback = Assert::isCallable($function);
                $result[$name] = static fn(string $name): FunctionNode => Assert::isInstanceOf($callback($name), \Doctrine\ORM\Query\AST\Functions\FunctionNode::class);
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
        $config->setProxyDir(dir: $options->getProxyDir());
        $config->setProxyNamespace(ns: $options->getProxyNamespace());

        $config->setEntityNamespaces(entityNamespaces: Value::stringMap($options->getEntityNamespaces()));

        $config->setCustomDatetimeFunctions(functions: $this->dqlFunctions($options->getDatetimeFunctions()));
        $config->setCustomStringFunctions(functions: $this->dqlFunctions($options->getStringFunctions()));
        foreach ($this->dqlFunctions($options->getNumericFunctions()) as $name => $function) {
            $config->addCustomNumericFunction($name, $function);
        }

        if ($options->getClassMetadataFactoryName() !== null) {
            $config->setClassMetadataFactoryName(Value::classString($options->getClassMetadataFactoryName()));
        }

        if ($options->getNamedQueries() !== [] || $options->getNamedNativeQueries() !== []) {
            throw new ServiceNotCreatedException(
                'Doctrine ORM no longer supports named queries; configure queries in repositories instead.',
            );
        }

        /** @mago-var mixed $hydrator */
        foreach ($options->getCustomHydrationModes() as $modeName => $hydrator) {
            Assert::string($hydrator);
            Assert::isAOf($hydrator, AbstractHydrator::class);
            $config->addCustomHydrationMode(modeName: Assert::string($modeName), hydrator: $hydrator);
        }

        /** @mago-var mixed $class */
        foreach ($options->getFilters() as $name => $class) {
            Assert::string($class);
            Assert::isAOf($class, SQLFilter::class);
            $config->addFilter(name: Assert::string($name), className: $class);
        }

        $config->setMetadataCache(Assert::isInstanceOf($container->get($options->getMetadataCache()), \Psr\Cache\CacheItemPoolInterface::class));
        $config->setQueryCache(Assert::isInstanceOf($container->get($options->getQueryCache()), \Psr\Cache\CacheItemPoolInterface::class));
        $config->setResultCache(Assert::isInstanceOf($container->get($options->getResultCache()), \Psr\Cache\CacheItemPoolInterface::class));
        $config->setHydrationCache(Assert::isInstanceOf($container->get($options->getHydrationCache()), \Psr\Cache\CacheItemPoolInterface::class));
        $config->setMetadataDriverImpl(driverImpl: Assert::isInstanceOf($container->get($options->getDriver()), \Doctrine\Persistence\Mapping\Driver\MappingDriver::class));

        $namingStrategy = $options->getNamingStrategy();
        if ($namingStrategy) {
            if (is_string(value: $namingStrategy)) {
                if (!$container->has($namingStrategy)) {
                    throw new InvalidArgumentException(message: sprintf(
                        'Naming strategy "%s" not found',
                        $namingStrategy,
                    ));
                }

                $config->setNamingStrategy(namingStrategy: Assert::isInstanceOf($container->get($namingStrategy), \Doctrine\ORM\Mapping\NamingStrategy::class));
            } else {
                $config->setNamingStrategy(namingStrategy: $namingStrategy);
            }
        }

        $quoteStrategy = $options->getQuoteStrategy();
        if ($quoteStrategy) {
            if (is_string(value: $quoteStrategy)) {
                if (!$container->has($quoteStrategy)) {
                    throw new InvalidArgumentException(message: sprintf(
                        'Quote strategy "%s" not found',
                        $quoteStrategy,
                    ));
                }

                $config->setQuoteStrategy(quoteStrategy: Assert::isInstanceOf($container->get($quoteStrategy), \Doctrine\ORM\Mapping\QuoteStrategy::class));
            } else {
                $config->setQuoteStrategy(quoteStrategy: $quoteStrategy);
            }
        }

        $repositoryFactory = $options->getRepositoryFactory();
        if ($repositoryFactory) {
            if (is_string(value: $repositoryFactory)) {
                if (!$container->has($repositoryFactory)) {
                    throw new InvalidArgumentException(message: sprintf(
                        'Repository factory "%s" not found',
                        $repositoryFactory,
                    ));
                }

                $config->setRepositoryFactory(repositoryFactory: Assert::isInstanceOf($container->get($repositoryFactory), \Doctrine\ORM\Repository\RepositoryFactory::class));
            } else {
                $config->setRepositoryFactory(repositoryFactory: $repositoryFactory);
            }
        }

        $entityListenerResolver = $options->getEntityListenerResolver();
        if ($entityListenerResolver) {
            if ($entityListenerResolver instanceof EntityListenerResolver) {
                $config->setEntityListenerResolver(resolver: $entityListenerResolver);
            } else {
                $config->setEntityListenerResolver(resolver: Assert::isInstanceOf($container->get($entityListenerResolver), \Doctrine\ORM\Mapping\EntityListenerResolver::class));
            }
        }

        $secondLevelCache = $options->getSecondLevelCache();

        if (Value::call($secondLevelCache, 'isEnabled')) {
            $regionsConfig = new RegionsConfiguration(
                defaultLifetime: Assert::integer(Value::call($secondLevelCache, 'getDefaultLifetime')),
                defaultLockLifetime: Assert::integer(Value::call($secondLevelCache, 'getDefaultLockLifetime')),
            );

            /**
             * @mago-var mixed $regionName
             * @mago-var mixed $regionConfig
             */
            foreach (Assert::isIterable(Value::call($secondLevelCache, 'getRegions')) as $regionName => $regionConfig) {
                if (isset($regionConfig['lifetime'])) {
                    $regionsConfig->setLifetime(name: Assert::string($regionName), lifetime: Assert::integer($regionConfig['lifetime']));
                }

                if (!isset($regionConfig['lock_lifetime'])) {
                    continue;
                }

                $regionsConfig->setLockLifetime(name: Assert::string($regionName), lifetime: Assert::integer($regionConfig['lock_lifetime']));
            }

            // As Second Level Cache caches queries results, we reuse the result cache impl
            $cacheFactory = new DefaultCacheFactory(regionsConfig: $regionsConfig, cacheItemPool: Assert::isInstanceOf(
                $config->getResultCache(),
                CacheItemPoolInterface::class,
            ));
            $cacheFactory->setFileLockRegionDirectory(fileLockRegionDirectory: Assert::string(Value::call(
                $secondLevelCache,
                'getFileLockRegionDirectory',
            )));

            $cacheConfiguration = new CacheConfiguration();
            $cacheConfiguration->setCacheFactory(factory: $cacheFactory);
            $cacheConfiguration->setRegionsConfiguration(regionsConfig: $regionsConfig);

            $config->setSecondLevelCacheEnabled();
            $config->setSecondLevelCacheConfiguration(cacheConfig: $cacheConfiguration);
        }

        $schemaAssetsFilter = $options->getSchemaAssetsFilter();
        if ($schemaAssetsFilter) {
            $config->setSchemaAssetsFilter(schemaAssetsFilter: $schemaAssetsFilter);
        }

        $className = $options->getDefaultRepositoryClassName();
        if ($className) {
            Assert::isAOf($className, EntityRepository::class);
            $config->setDefaultRepositoryClassName(className: $className);
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
