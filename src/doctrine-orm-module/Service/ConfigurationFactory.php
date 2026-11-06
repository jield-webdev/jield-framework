<?php

declare(strict_types=1);

namespace DoctrineORMModule\Service;

use Doctrine\ORM\Cache\CacheConfiguration;
use Doctrine\ORM\Cache\DefaultCacheFactory;
use Doctrine\ORM\Cache\RegionsConfiguration;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\Mapping\EntityListenerResolver;
use DoctrineORMModule\Options\Configuration as DoctrineORMModuleConfiguration;
use DoctrineORMModule\Service\DBALConfigurationFactory as DoctrineConfigurationFactory;
use Laminas\ServiceManager\Exception\InvalidArgumentException;
use Psr\Container\ContainerInterface;

use function is_string;
use function method_exists;
use function sprintf;

final class ConfigurationFactory extends DoctrineConfigurationFactory
{
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
        $config->setProxyDir(dir: \Jield\Stdlib\Value::string($options->getProxyDir()));
        $config->setProxyNamespace(ns: \Jield\Stdlib\Value::string($options->getProxyNamespace()));

        $config->setEntityNamespaces(entityNamespaces: \Jield\Stdlib\Value::stringMap($options->getEntityNamespaces()));

        $config->setCustomDatetimeFunctions(functions: $options->getDatetimeFunctions());
        $config->setCustomStringFunctions(functions: $options->getStringFunctions());
        $config->setCustomNumericFunctions(functions: $options->getNumericFunctions());

        if ($options->getClassMetadataFactoryName() !== null) {
            $config->setClassMetadataFactoryName(\Jield\Stdlib\Value::classString($options->getClassMetadataFactoryName()));
        }

        if ($options->getNamedQueries() !== [] || $options->getNamedNativeQueries() !== []) {
            throw new \Laminas\ServiceManager\Exception\ServiceNotCreatedException(
                'Doctrine ORM no longer supports named queries; configure queries in repositories instead.',
            );
        }

        /** @var mixed $modeName
         * @var mixed $hydrator
         */
        foreach ($options->getCustomHydrationModes() as $modeName => $hydrator) {
            $config->addCustomHydrationMode(modeName: \Jield\Stdlib\Value::string($modeName), hydrator: \Jield\Stdlib\Value::subclass($hydrator, \Doctrine\ORM\Internal\Hydration\AbstractHydrator::class));
        }

        /** @var mixed $name
         * @var mixed $class
         */
        foreach ($options->getFilters() as $name => $class) {
            $config->addFilter(name: \Jield\Stdlib\Value::string($name), className: \Jield\Stdlib\Value::subclass($class, \Doctrine\ORM\Query\Filter\SQLFilter::class));
        }

        $config->setMetadataCache(\Jield\Stdlib\Value::instance($container->get(\Jield\Stdlib\Value::string($options->getMetadataCache())), \Psr\Cache\CacheItemPoolInterface::class));
        $config->setQueryCache(\Jield\Stdlib\Value::instance($container->get(\Jield\Stdlib\Value::string($options->getQueryCache())), \Psr\Cache\CacheItemPoolInterface::class));
        $config->setResultCache(\Jield\Stdlib\Value::instance($container->get(\Jield\Stdlib\Value::string($options->getResultCache())), \Psr\Cache\CacheItemPoolInterface::class));
        $config->setHydrationCache(\Jield\Stdlib\Value::instance($container->get(\Jield\Stdlib\Value::string($options->getHydrationCache())), \Psr\Cache\CacheItemPoolInterface::class));
        $config->setMetadataDriverImpl(driverImpl: \Jield\Stdlib\Value::instance($container->get(\Jield\Stdlib\Value::string($options->getDriver())), \Doctrine\Persistence\Mapping\Driver\MappingDriver::class));

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

                $config->setNamingStrategy(namingStrategy: \Jield\Stdlib\Value::instance($container->get($namingStrategy), \Doctrine\ORM\Mapping\NamingStrategy::class));
            } else {
                $config->setNamingStrategy(namingStrategy: \Jield\Stdlib\Value::instance($namingStrategy, \Doctrine\ORM\Mapping\NamingStrategy::class));
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

                $config->setQuoteStrategy(quoteStrategy: \Jield\Stdlib\Value::instance($container->get($quoteStrategy), \Doctrine\ORM\Mapping\QuoteStrategy::class));
            } else {
                $config->setQuoteStrategy(quoteStrategy: \Jield\Stdlib\Value::instance($quoteStrategy, \Doctrine\ORM\Mapping\QuoteStrategy::class));
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

                $config->setRepositoryFactory(repositoryFactory: \Jield\Stdlib\Value::instance($container->get($repositoryFactory), \Doctrine\ORM\Repository\RepositoryFactory::class));
            } else {
                $config->setRepositoryFactory(repositoryFactory: \Jield\Stdlib\Value::instance($repositoryFactory, \Doctrine\ORM\Repository\RepositoryFactory::class));
            }
        }

        /** @var mixed $entityListenerResolver */
        $entityListenerResolver = $options->getEntityListenerResolver();
        if ($entityListenerResolver) {
            if ($entityListenerResolver instanceof EntityListenerResolver) {
                $config->setEntityListenerResolver(resolver: $entityListenerResolver);
            } else {
                $config->setEntityListenerResolver(resolver: \Jield\Stdlib\Value::instance($container->get(\Jield\Stdlib\Value::string($entityListenerResolver)), \Doctrine\ORM\Mapping\EntityListenerResolver::class));
            }
        }

        /** @var mixed $secondLevelCache */
        $secondLevelCache = $options->getSecondLevelCache();

        if (\Jield\Stdlib\Value::call($secondLevelCache, 'isEnabled')) {
            $regionsConfig = new RegionsConfiguration(
                defaultLifetime: \Jield\Stdlib\Value::int(\Jield\Stdlib\Value::call($secondLevelCache, 'getDefaultLifetime')),
                defaultLockLifetime: \Jield\Stdlib\Value::int(\Jield\Stdlib\Value::call($secondLevelCache, 'getDefaultLockLifetime')),
            );

            /** @var mixed $regionName
             * @var mixed $regionConfig
             */
            foreach (\Jield\Stdlib\Value::iterable(\Jield\Stdlib\Value::call($secondLevelCache, 'getRegions')) as $regionName => $regionConfig) {
                if (isset($regionConfig['lifetime'])) {
                    $regionsConfig->setLifetime(name: \Jield\Stdlib\Value::string($regionName), lifetime: \Jield\Stdlib\Value::int($regionConfig['lifetime']));
                }

                if (!isset($regionConfig['lock_lifetime'])) {
                    continue;
                }

                $regionsConfig->setLockLifetime(name: \Jield\Stdlib\Value::string($regionName), lifetime: \Jield\Stdlib\Value::int($regionConfig['lock_lifetime']));
            }

            // As Second Level Cache caches queries results, we reuse the result cache impl
            $cacheFactory = new DefaultCacheFactory(
                regionsConfig: $regionsConfig,
                cacheItemPool: \Jield\Stdlib\Value::instance($config->getResultCache(), \Psr\Cache\CacheItemPoolInterface::class),
            );
            $cacheFactory->setFileLockRegionDirectory(
                fileLockRegionDirectory: \Jield\Stdlib\Value::string(\Jield\Stdlib\Value::call($secondLevelCache, 'getFileLockRegionDirectory')),
            );

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
                $config->setSchemaAssetsFilter(schemaAssetsFilter: \Jield\Stdlib\Value::callback($schemaAssetsFilter));
            }
        }

        /** @var mixed $className */
        $className = $options->getDefaultRepositoryClassName();
        if ($className) {
            $config->setDefaultRepositoryClassName(className: \Jield\Stdlib\Value::subclass($className, \Doctrine\ORM\EntityRepository::class));
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
