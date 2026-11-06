<?php

declare(strict_types=1);

namespace Jield\Authorize;

use Jield\Authorize\Collector\RoleCollector;
use Jield\Authorize\Controller\Plugin\IsAllowed as ControllerIsAllowedPlugin;
use Jield\Authorize\Controller\Plugin\IsAllowedFactory as ControllerIsAllowedFactory;
use Jield\Authorize\Factory\AssertionServiceFactory;
use Jield\Authorize\Factory\AuthenticationIdentityProviderFactory;
use Jield\Authorize\Factory\AuthorizeServiceFactory;
use Jield\Authorize\Factory\CacheFactory;
use Jield\Authorize\Factory\CacheKeyGeneratorFactory;
use Jield\Authorize\Factory\ConfigResourceProviderServiceFactory;
use Jield\Authorize\Factory\ConfigRoleProviderServiceFactory;
use Jield\Authorize\Factory\ConfigRuleProviderServiceFactory;
use Jield\Authorize\Factory\ConfigServiceFactory;
use Jield\Authorize\Factory\ControllerGuardServiceFactory;
use Jield\Authorize\Factory\GuardsServiceFactory;
use Jield\Authorize\Factory\IdentityProviderServiceFactory;
use Jield\Authorize\Factory\ObjectRepositoryRoleProviderFactory;
use Jield\Authorize\Factory\ResourceProvidersServiceFactory;
use Jield\Authorize\Factory\RoleCollectorServiceFactory;
use Jield\Authorize\Factory\RoleProvidersServiceFactory;
use Jield\Authorize\Factory\RouteGuardServiceFactory;
use Jield\Authorize\Factory\RuleProvidersServiceFactory;
use Jield\Authorize\Factory\RuleWithAssertionFactory;
use Jield\Authorize\Guard\Controller;
use Jield\Authorize\Guard\Route;
use Jield\Authorize\Provider\Identity\AuthenticationIdentityProvider;
use Jield\Authorize\Provider\Identity\ProviderInterface as IdentityProviderInterface;
use Jield\Authorize\Provider\Resource\Config as ResourceConfigProvider;
use Jield\Authorize\Provider\Role\Config as RoleConfigProvider;
use Jield\Authorize\Provider\Role\ObjectRepositoryProvider;
use Jield\Authorize\Provider\Rule\Config as RuleConfigProvider;
use Jield\Authorize\Rule\RulesWithAssertion;
use Jield\Authorize\Service\AssertionService;
use Jield\Authorize\Service\AuthorizeService;
use Jield\Authorize\View\Helper\IsAllowed as ViewIsAllowedHelper;
use Jield\Authorize\View\Helper\IsAllowedFactory as ViewIsAllowedFactory;
use Jield\Authorize\View\RedirectionStrategy;
use Jield\Authorize\View\UnauthorizedStrategy;
use Laminas\Authentication\AuthenticationService;
use Laminas\ServiceManager\AbstractFactory\ConfigAbstractFactory;

final class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'bjyauthorize' => $this->getBjyAuthorizeConfig(),
            ConfigAbstractFactory::class => $this->getConfigAbstractFactory(),
            'dependencies' => $this->getDependencyConfig(),
            'service_manager' => $this->getDependencyConfig(),
            'controller_plugins' => $this->getControllerPluginConfig(),
            'view_helpers' => $this->getViewHelperConfig(),
            'view_manager' => $this->getViewManagerConfig(),
            'laminas-developer-tools' => $this->getDeveloperToolsConfig(),
        ];
    }

    public function getDependencyConfig(): array
    {
        return [
            'aliases' => [],
            'factories' => [
                Cache::class => CacheFactory::class,
                CacheKeyGenerator::class => CacheKeyGeneratorFactory::class,
                Config::class => ConfigServiceFactory::class,
                Guards::class => GuardsServiceFactory::class,
                RoleProviders::class => RoleProvidersServiceFactory::class,
                ResourceProviders::class => ResourceProvidersServiceFactory::class,
                RuleProviders::class => RuleProvidersServiceFactory::class,
                RoleCollector::class => RoleCollectorServiceFactory::class,
                Controller::class => ControllerGuardServiceFactory::class,
                Route::class => RouteGuardServiceFactory::class,
                AuthenticationIdentityProvider::class => AuthenticationIdentityProviderFactory::class,
                IdentityProviderInterface::class => IdentityProviderServiceFactory::class,
                ResourceConfigProvider::class => ConfigResourceProviderServiceFactory::class,
                RoleConfigProvider::class => ConfigRoleProviderServiceFactory::class,
                ObjectRepositoryProvider::class => ObjectRepositoryRoleProviderFactory::class,
                RuleConfigProvider::class => ConfigRuleProviderServiceFactory::class,
                AuthorizeService::class => AuthorizeServiceFactory::class,
                AssertionService::class => AssertionServiceFactory::class,
                RulesWithAssertion::class => RuleWithAssertionFactory::class,
                UnauthorizedStrategy::class => ConfigAbstractFactory::class,
            ],
            'invokables' => [
                RedirectionStrategy::class,
            ],
        ];
    }

    public function getConfigAbstractFactory(): array
    {
        return [
            UnauthorizedStrategy::class => [
                AuthenticationService::class,
                Config::class,
            ],
        ];
    }

    public function getBjyAuthorizeConfig(): array
    {
        return [
            // default role for unauthenticated users
            'default_role' => 'guest',

            // default role for authenticated users (if using the
            // 'Jield\Authorize\Provider\Identity\AuthenticationIdentityProvider' identity provider)
            'authenticated_role' => 'user',

            // identity provider service name
            'identity_provider' => AuthenticationIdentityProvider::class,

            // Role providers to be used to load all available roles into Laminas\Permissions\Acl\Acl
            // Keys are the provider service names, values are the options to be passed to the provider
            'role_providers' => [],

            // Resource providers to be used to load all available resources into Laminas\Permissions\Acl\Acl
            // Keys are the provider service names, values are the options to be passed to the provider
            'resource_providers' => [],

            // Rule providers to be used to load all available rules into Laminas\Permissions\Acl\Acl
            // Keys are the provider service names, values are the options to be passed to the provider
            'rule_providers' => [],

            // Guard listeners to be attached to the application event manager
            'guards' => [],

            // strategy service name for the strategy listener to be used when permission-related errors are detected
            'unauthorized_strategy' => UnauthorizedStrategy::class,

            // Template name for the unauthorized strategy
            'template' => 'error/403',

            // Flag if cache should be enabled or not
            'cache_enabled' => true,

            // cache options have to be compatible with Laminas\Cache\StorageAdapterFactoryInterface::create
            'cache_options' => [
                'adapter' => [
                    'name' => 'memory',
                ],
                'plugins' => [
                    [
                        'name' => 'serializer',
                    ],
                ],
            ],

            // Key used by the cache for caching the acl
            'cache_key' => 'bjyauthorize_acl',
        ];
    }

    public function getControllerPluginConfig(): array
    {
        return [
            'factories' => [
                ControllerIsAllowedPlugin::class => ControllerIsAllowedFactory::class,
                'isAllowed' => ControllerIsAllowedFactory::class,
            ],
        ];
    }

    public function getViewHelperConfig(): array
    {
        return [
            'factories' => [
                ViewIsAllowedHelper::class => ViewIsAllowedFactory::class,
                'isAllowed' => ViewIsAllowedFactory::class,
            ],
        ];
    }

    public function getViewManagerConfig(): array
    {
        return [
            'template_map' => [
                'error/403' => __DIR__ . '/../view/error/403.phtml',
                'laminas-developer-tools/toolbar/bjy-authorize-role' =>
                    __DIR__ . '/../view/laminas-developer-tools/toolbar/bjy-authorize-role.phtml',
            ],
        ];
    }

    public function getDeveloperToolsConfig(): array
    {
        return [
            'profiler' => [
                'collectors' => [
                    'bjy_authorize_role_collector' => RoleCollector::class,
                ],
            ],
            'toolbar' => [
                'entries' => [
                    'bjy_authorize_role_collector' => 'laminas-developer-tools/toolbar/bjy-authorize-role',
                ],
            ],
        ];
    }
}
