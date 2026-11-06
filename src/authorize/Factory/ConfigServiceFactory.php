<?php

declare(strict_types=1);

namespace Jield\Authorize\Factory;

use Doctrine\ORM\EntityManager;
use Jield\Authorize\Provider\Role\ObjectRepositoryProvider;
use Laminas\Permissions\Acl\Role\RoleInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

class ConfigServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): mixed
    {
        /** @var mixed $config */
        $config = $container->get('config');

        /** @var mixed $bjyAuthorizeConfig */
        $bjyAuthorizeConfig = \Jield\Stdlib\Value::accessible($config)['bjyauthorize'];
        /** @var mixed $jieldAuthorizeConfig */
        $jieldAuthorizeConfig = \Jield\Stdlib\Value::accessible($config)['jield_authorize'];

        //Check if the role entity class implements the RoleInterface
        Assert::implementsInterface(value: \Jield\Stdlib\Value::accessible($jieldAuthorizeConfig)['role_entity_class'], interface: RoleInterface::class);

        //Inject the role_provider
        \Jield\Stdlib\Value::setPath($bjyAuthorizeConfig, ['role_providers'], [
            ObjectRepositoryProvider::class => [
                'object_manager' => EntityManager::class,
                'role_entity_class' => \Jield\Stdlib\Value::accessible($jieldAuthorizeConfig)['role_entity_class'],
            ],
        ]);

        //Override the cache_enabled
        \Jield\Stdlib\Value::setPath($bjyAuthorizeConfig, ['cache_enabled'], \Jield\Stdlib\Value::accessible($jieldAuthorizeConfig)['cache_enabled']);

        //Override the default role
        \Jield\Stdlib\Value::setPath($bjyAuthorizeConfig, ['default_role'], \Jield\Stdlib\Value::accessible($jieldAuthorizeConfig)['default_role']);

        \Jield\Stdlib\Value::setPath($bjyAuthorizeConfig, ['cache_key'], 'jield-authorize-cache');

        //Override the authenticated role
        \Jield\Stdlib\Value::setPath($bjyAuthorizeConfig, ['authenticated_role'], \Jield\Stdlib\Value::accessible($jieldAuthorizeConfig)['authenticated_role']);

        \Jield\Stdlib\Value::setPath($bjyAuthorizeConfig, ['login_route'], $jieldAuthorizeConfig['login_route'] ?? 'user/login');

        return $bjyAuthorizeConfig;
    }
}
