<?php

declare(strict_types=1);

namespace Jield\ApiTools\Rpc\Factory;

use Jield\ApiTools\Rpc\OptionsListener;
use Psr\Container\ContainerInterface;

class OptionsListenerFactory
{
    public function __invoke(ContainerInterface $container): OptionsListener
    {
        return new OptionsListener(config: $this->getConfig(container: $container));
    }

    /**
     * Attempt to marshal configuration from the "config" service.
     *
     */
    private function getConfig(ContainerInterface $container): array
    {
        if (!$container->has('config')) {
            return [];
        }

        /** @mago-var mixed $config */
        $config = $container->get('config');
        if (!isset($config['api-tools-rpc'])) {
            return [];
        }

        return \Webmozart\Assert\Assert::isArray($config['api-tools-rpc']);
    }
}
