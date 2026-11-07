<?php

declare(strict_types=1);

namespace Jield\ApiTools\Rpc\Factory;

use Jield\ApiTools\Rpc\OptionsListener;
use Psr\Container\ContainerInterface;

class OptionsListenerFactory
{
    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): OptionsListener
    {
        return new OptionsListener(config: $this->getConfig(container: $container));
    }

    /**
     * Attempt to marshal configuration from the "config" service.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    private function getConfig(ContainerInterface $container): array
    {
        if (!$container->has('config')) {
            return [];
        }

        /** @mago-var mixed $config */
        $config = $container->get('config');
        if (($config['api-tools-rpc'] ?? null) === null) {
            return [];
        }

        return \Webmozart\Assert\Assert::isArray($config['api-tools-rpc']);
    }
}
