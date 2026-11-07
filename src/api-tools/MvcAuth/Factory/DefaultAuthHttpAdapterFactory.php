<?php

declare(strict_types=1);

namespace Jield\ApiTools\MvcAuth\Factory;

use Laminas\Authentication\Adapter\Http as HttpAuth;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;

/**
 * Factory for creating the DefaultAuthHttpAdapterFactory from configuration.
 */
class DefaultAuthHttpAdapterFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @param string     $requestedName
     */
    #[Override]
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): false|HttpAuth
    {
        // If no configuration present, nothing to create
        if (!$container->has('config')) {
            return false;
        }

        /** @mago-var mixed $config */
        $config = $container->get('config');

        // If no HTTP adapter configuration present, nothing to create
        if (($config['api-tools-mvc-auth']['authentication']['http'] ?? null) === null) {
            return false;
        }

        return HttpAdapterFactory::factory(
            config: \Webmozart\Assert\Assert::isArray($config['api-tools-mvc-auth']['authentication']['http']),
            container: $container,
        );
    }
}
