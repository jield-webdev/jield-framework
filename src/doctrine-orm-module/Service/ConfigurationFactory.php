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
        foreach ($functions as $name => $function) {
            if (is_string($function) && !is_callable($function)) {
                Assert::isAOf($function, FunctionNode::class);
                $result[$name] = $function;
            } else {
                $callback = Assert::isCallable($function);
                $result[$name] = static fn(string $name): FunctionNode => $callback($name);
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

        $config->setEntityNamespaces(entityNamespaces: $options->getEntityNamespaces());

        $config->setCustomDatetimeFunctions(functions: $this->dqlFunctions($options->getDatetimeFunctions()));
        $config->setCustomStringFunctions(functions: $this->dqlFunctions($options->getStringFunctions()));
        foreach ($this->dqlFunctions($options->getNumericFunctions()) as $name => $function) {
            $config->addCustomNumericFunction($name, $function);
        }

        if ($options->getClassMetadataFactoryName() !== null) {
            $config->setClassMetadataFactoryName($options->getClassMetadataFactoryName());
        }

        if ($options->getNamedQueries() !== [] || $options->getNamedNativeQueries() !== []) {
            throw new ServiceNotCreatedException(
                'Doctrine ORM no longer supports named queries; configure queries in repositories instead.',
            );
        }

        foreach ($options->getCustomHydrationModes() as $modeName => $hydrator) {
            Assert::string($hydrator);
            Assert::isAOf($hydrator, AbstractHydrator::class);
            $config->addCustomHydrationMode(modeName: $modeName, hydrator: $hydrator);
        }

        foreach ($options->getFilters() as $name => $class) {
            Assert::string($class);
            Assert::isAOf($class, SQLFilter::class);
            $config->addFilter(name: $name, className: $class);
        }

        $config->setMetadataCache($container->get($options->getMetadataCache()));
        $config->setQueryCache($container->get($options->getQueryCache()));
        $config->setResultCache($container->get($options->getResultCache()));
        $config->setHydrationCache($container->get($options->getHydrationCache()));
        $config->setMetadataDriverImpl(driverImpl: $container->get($options->getDriver()));

        $namingStrategy = $options->getNamingStrategy();
        if ($namingStrategy) {
            if (is_string(value: $namingStrategy)) {
                if (!$container->has($namingStrategy)) {
                    throw new InvalidArgumentException(message: sprintf(
                        'Naming strategy "%s" not found',
                        $namingStrategy,
                    ));
                }

                $config->setNamingStrategy(namingStrategy: $container->get($namingStrategy));
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

                $config->setQuoteStrategy(quoteStrategy: $container->get($quoteStrategy));
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

                $config->setRepositoryFactory(repositoryFactory: $container->get($repositoryFactory));
            } else {
                $config->setRepositoryFactory(repositoryFactory: $repositoryFactory);
            }
        }

        $entityListenerResolver = $options->getEntityListenerResolver();
        if ($entityListenerResolver) {
            if ($entityListenerResolver instanceof EntityListenerResolver) {
                $config->setEntityListenerResolver(resolver: $entityListenerResolver);
            } else {
                $config->setEntityListenerResolver(resolver: $container->get($entityListenerResolver));
            }
        }

        $secondLevelCache = $options->getSecondLevelCache();

        if (Value::call($secondLevelCache, 'isEnabled')) {
            $regionsConfig = new RegionsConfiguration(
                defaultLifetime: Value::call($secondLevelCache, 'getDefaultLifetime'),
                defaultLockLifetime: Value::call($secondLevelCache, 'getDefaultLockLifetime'),
            );

            foreach (Assert::isIterable(Value::call($secondLevelCache, 'getRegions')) as $regionName => $regionConfig) {
                if (isset($regionConfig['lifetime'])) {
                    $regionsConfig->setLifetime(name: $regionName, lifetime: $regionConfig['lifetime']);
                }

                if (!isset($regionConfig['lock_lifetime'])) {
                    continue;
                }

                $regionsConfig->setLockLifetime(name: $regionName, lifetime: $regionConfig['lock_lifetime']);
            }

            // As Second Level Cache caches queries results, we reuse the result cache impl
            $cacheFactory = new DefaultCacheFactory(regionsConfig: $regionsConfig, cacheItemPool: Assert::isInstanceOf(
                $config->getResultCache(),
                CacheItemPoolInterface::class,
            ));
            $cacheFactory->setFileLockRegionDirectory(fileLockRegionDirectory: Value::call(
                $secondLevelCache,
                'getFileLockRegionDirectory',
            ));

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
