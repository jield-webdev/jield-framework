<?php

declare(strict_types=1);

namespace Jield\Authorize\Factory;

use Jield\Authorize\Config;
use Jield\Authorize\View\UnauthorizedStrategy;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

/**
 * Factory responsible of instantiating {@see \Jield\Authorize\View\UnauthorizedStrategy}
 */
class UnauthorizedStrategyServiceFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @see \Laminas\ServiceManager\Factory\FactoryInterface::__invoke()
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null,
    ): UnauthorizedStrategy {
        $config = \Jield\Stdlib\Value::array($container->get(Config::class));
        $strategy = new UnauthorizedStrategy(
            authenticationService: \Jield\Stdlib\Service::get($container, \Laminas\Authentication\AuthenticationService::class),
            config: $config,
        );
        $strategy->setTemplate(\Jield\Stdlib\Value::string($config['template']));
        return $strategy;
    }
}
