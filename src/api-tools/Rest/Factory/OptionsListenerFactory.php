<?php

declare(strict_types=1);

namespace Jield\ApiTools\Rest\Factory;

use Jield\ApiTools\Rest\Listener\OptionsListener;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;

use function array_key_exists;
use function is_array;

class OptionsListenerFactory implements FactoryInterface
{
    /**
     * Create and return an OptionsListener instance.
     *
     * @param string $requestedName
     */
    #[Override]
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): OptionsListener
    {
        return new OptionsListener(config: $this->getConfig(container: $container));
    }

    /**
     * Retrieve api-tools-rest config from the container, if available.
     *
     */
    private function getConfig(ContainerInterface $container): array
    {
        if (!$container->has('config')) {
            return [];
        }

        /** @mago-var mixed $config */
        $config = $container->get('config');

        if (!array_key_exists(key: 'api-tools-rest', array: \Webmozart\Assert\Assert::isArray($config)) || !is_array(value: $config['api-tools-rest'])) {
            return [];
        }

        return $config['api-tools-rest'];
    }
}
