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
    public function __invoke(ContainerInterface $container, string $requestedName, array|null $options = null): Configuration
    {
        $options = $this->getOptions(container: $container);
        $config  = new Configuration();

        $config->setAutoGenerateProxyClasses(autoGenerate: $options->getGenerateProxies());
        $config->setProxyDir(dir: $options->getProxyDir());
        $config->setProxyNamespace(ns: $options->getProxyNamespace());

        $config->setEntityNamespaces(entityNamespaces: $options->getEntityNamespaces());

        $config->setCustomDatetimeFunctions(functions: $options->getDatetimeFunctions());
        $config->setCustomStringFunctions(functions: $options->getStringFunctions());
        $config->setCustomNumericFunctions(functions: $options->getNumericFunctions());

        $config->setClassMetadataFactoryName(cmfName: $options->getClassMetadataFactoryName());

        foreach ($options->getNamedQueries() as $name => $query) {
            $config->addNamedQuery($name, $query);
        }

        foreach ($options->getNamedNativeQueries() as $name => $query) {
            $config->addNamedNativeQuery($name, $query['sql'], new $query['rsm']());
        }

        foreach ($options->getCustomHydrationModes() as $modeName => $hydrator) {
            $config->addCustomHydrationMode(modeName: $modeName, hydrator: $hydrator);
        }

        foreach ($options->getFilters() as $name => $class) {
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
                if (! $container->has(id: $namingStrategy)) {
                    throw new InvalidArgumentException(message: sprintf('Naming strategy "%s" not found', $namingStrategy));
                }

                $config->setNamingStrategy(namingStrategy: $container->get($namingStrategy));
            } else {
                $config->setNamingStrategy(namingStrategy: $namingStrategy);
            }
        }

        $quoteStrategy = $options->getQuoteStrategy();
        if ($quoteStrategy) {
            if (is_string(value: $quoteStrategy)) {
                if (! $container->has(id: $quoteStrategy)) {
                    throw new InvalidArgumentException(message: sprintf('Quote strategy "%s" not found', $quoteStrategy));
                }

                $config->setQuoteStrategy(quoteStrategy: $container->get($quoteStrategy));
            } else {
                $config->setQuoteStrategy(quoteStrategy: $quoteStrategy);
            }
        }

        $repositoryFactory = $options->getRepositoryFactory();
        if ($repositoryFactory) {
            if (is_string(value: $repositoryFactory)) {
                if (! $container->has(id: $repositoryFactory)) {
                    throw new InvalidArgumentException(
                        message: sprintf('Repository factory "%s" not found', $repositoryFactory),
                    );
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

        if ($secondLevelCache->isEnabled()) {
            $regionsConfig = new RegionsConfiguration(
                defaultLifetime: $secondLevelCache->getDefaultLifetime(),
                defaultLockLifetime: $secondLevelCache->getDefaultLockLifetime(),
            );

            foreach ($secondLevelCache->getRegions() as $regionName => $regionConfig) {
                if (isset($regionConfig['lifetime'])) {
                    $regionsConfig->setLifetime(name: $regionName, lifetime: $regionConfig['lifetime']);
                }

                if (! isset($regionConfig['lock_lifetime'])) {
                    continue;
                }

                $regionsConfig->setLockLifetime(name: $regionName, lifetime: $regionConfig['lock_lifetime']);
            }

            // As Second Level Cache caches queries results, we reuse the result cache impl
            $cacheFactory = new DefaultCacheFactory(regionsConfig: $regionsConfig, cacheItemPool: $config->getResultCache());
            $cacheFactory->setFileLockRegionDirectory(fileLockRegionDirectory: $secondLevelCache->getFileLockRegionDirectory());

            $cacheConfiguration = new CacheConfiguration();
            $cacheConfiguration->setCacheFactory(factory: $cacheFactory);
            $cacheConfiguration->setRegionsConfiguration(regionsConfig: $regionsConfig);

            $config->setSecondLevelCacheEnabled();
            $config->setSecondLevelCacheConfiguration(cacheConfig: $cacheConfiguration);
        }

        // only works for DBAL 2.x, not for 3.x
        if (method_exists(object_or_class: $config, method: 'setFilterSchemaAssetsExpression')) {
            $filterSchemaAssetsExpression = $options->getFilterSchemaAssetsExpression();
            if ($filterSchemaAssetsExpression) {
                $config->setFilterSchemaAssetsExpression($filterSchemaAssetsExpression);
            }
        }

        // DBAL 2.x
        if (method_exists(object_or_class: $config, method: 'setSchemaAssetsFilter')) {
            $schemaAssetsFilter = $options->getSchemaAssetsFilter();
            if ($schemaAssetsFilter) {
                $config->setSchemaAssetsFilter(schemaAssetsFilter: $schemaAssetsFilter);
            }
        }

        $className = $options->getDefaultRepositoryClassName();
        if ($className) {
            $config->setDefaultRepositoryClassName(className: $className);
        }

        $this->setupDBALConfiguration(container: $container, config: $config);

        return $config;
    }

    protected function getOptionsClass(): string
    {
        return DoctrineORMModuleConfiguration::class;
    }
}
