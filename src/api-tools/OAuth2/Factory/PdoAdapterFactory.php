<?php

declare(strict_types=1);

namespace Jield\ApiTools\OAuth2\Factory;

use Jield\ApiTools\OAuth2\Adapter\PdoAdapter;
use Jield\Stdlib\Value;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;

use function is_array;
use function sprintf;

final class PdoAdapterFactory implements FactoryInterface
{
    #[Override]
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): PdoAdapter
    {
        /** @mago-var mixed $config */
        $config = $container->get('config');

        /** @mago-var mixed $oauthConfig */
        $oauthConfig = Value::accessible($config)['api-tools-oauth2'];

        //Grab the params directly from the Doctrine Params
        /** @mago-var mixed $username */
        $username = $config['doctrine']['connection']['orm_default']['params']['user'] ?? null;
        /** @mago-var mixed $password */
        $password = $config['doctrine']['connection']['orm_default']['params']['password'] ?? null;
        /** @mago-var mixed $options */
        $options = $config['doctrine']['connection']['orm_default']['params']['driverOptions'] ?? [];

        $dsn = sprintf(
            'mysql:dbname=%s;host=%s',
            Value::scalar(Value::path($config, [
                'doctrine',
                'connection',
                'orm_default',
                'params',
                'dbname',
            ])),
            Value::scalar(Value::path($config, [
                'doctrine',
                'connection',
                'orm_default',
                'params',
                'host',
            ])),
        );

        $oauth2ServerConfig = [];
        if (isset($oauthConfig['storage_settings']) && is_array(value: $oauthConfig['storage_settings'])) {
            $oauth2ServerConfig = $oauthConfig['storage_settings'];
        }

        //Add 2 own options
        $oauth2ServerConfig['bcrypt_cost'] = 14;
        $oauth2ServerConfig['user_table'] = 'admin_user';

        return new PdoAdapter(connection: [
            'dsn' => $dsn,
            'username' => $username,
            'password' => $password,
            'options' => $options,
        ], config: $oauth2ServerConfig);
    }
}
