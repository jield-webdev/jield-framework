<?php

declare(strict_types=1);

namespace DoctrineModule;

use Jield\Authentication\Storage\Session as JieldSessionStorage;
use Jield\Cache\Storage\Adapter\Memory;

/**
 * Config provider for DoctrineORMModule config
 */
final class ConfigProvider
{
    /** @return array<non-empty-string, array> */
    public function __invoke(): array
    {
        return [
            'caches'             => $this->getCachesConfig(),
            'doctrine'           => $this->getDoctrineConfig(),
            'doctrine_factories' => $this->getDoctrineFactoryConfig(),
            'dependencies'       => $this->getDependencyConfig(),
            'validators'         => $this->getValidatorConfig(),
        ];
    }

    /**
     * Return application-level dependency configuration
     *
     * @return array<non-empty-string, array<non-empty-string, class-string>>
     */
    public function getDependencyConfig(): array
    {
        return [
            'invokables'         => ['DoctrineModule\Authentication\Storage\Session' => JieldSessionStorage::class],
            'factories'          => ['doctrine.cli' => Service\CliFactory::class],
            'abstract_factories' => ['DoctrineModule' => ServiceFactory\AbstractDoctrineServiceFactory::class],
        ];
    }

    /**
     * Default configuration for Doctrine module
     *
     * @return array<non-empty-string, array>
     */
    public function getDoctrineConfig(): array
    {
        return [
            //These authentication settings are a hack to tide things over until version 1.0
            //Normall doctrineModule should have no mention of odm or orm
            'authentication'        => [
                //default authentication options should be set in either the odm or orm modules
                'orm_default' => [],
            ],
            'authenticationadapter' => [
                'orm_default' => true,
            ],
            'authenticationstorage' => [
                'orm_default' => true,
            ],
            'authenticationservice' => [
                'orm_default' => true,
            ],
        ];
    }

    /**
     * Factory mappings - used to define which factory to use to instantiate a particular doctrine service type
     *
     * @return array<non-empty-string, class-string>
     */
    public function getDoctrineFactoryConfig(): array
    {
        return [
            'eventmanager'          => Service\EventManagerFactory::class,
            'driver'                => Service\DriverFactory::class,
            'authenticationadapter' => Service\Authentication\AdapterFactory::class,
            'authenticationstorage' => Service\Authentication\StorageFactory::class,
            'authenticationservice' => Service\Authentication\AuthenticationServiceFactory::class,
        ];
    }

    /** @return array<non-empty-string, array> */
    public function getValidatorConfig(): array
    {
        return [
            'aliases'   => [
                'DoctrineNoObjectExists' => Validator\NoObjectExists::class,
                'DoctrineObjectExists'   => Validator\ObjectExists::class,
                'DoctrineUniqueObject'   => Validator\UniqueObject::class,
            ],
            'factories' => [
                Validator\NoObjectExists::class => Validator\Service\NoObjectExistsFactory::class,
                Validator\ObjectExists::class   => Validator\Service\ObjectExistsFactory::class,
                Validator\UniqueObject::class   => Validator\Service\UniqueObjectFactory::class,
            ],
        ];
    }

    /** @return array<non-empty-string, array{adapter: string, options?: array, plugins?: array}> */
    public function getCachesConfig(): array
    {
        $defaultOptions = ['namespace' => 'DoctrineModule'];

        return [
            'doctrinemodule.cache.apcu'       => [
                'adapter' => 'apcu',
                'options' => $defaultOptions,
            ],
            'doctrinemodule.cache.filesystem' => [
                'adapter' => 'filesystem',
                'options' => $defaultOptions + [
                        'cache_dir'   => 'data/DoctrineModule/cache',
                        // We need to be slightly less restrictive than Filesystem defaults:
                        'key_pattern' => '/^[a-z0-9_\+\-\[\]\\\\$#]*$/Di',
                    ],
                'plugins' => [['name' => 'serializer']],
            ],
            'doctrinemodule.cache.redis'      => [
                'adapter' => 'redis',
                'options' => $defaultOptions + [
                        'server' => [
                            'host' => 'localhost',
                            'post' => 6379,
                        ],
                    ],
            ],
        ];
    }
}
