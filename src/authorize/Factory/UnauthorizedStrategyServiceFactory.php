<?php

declare(strict_types=1);

namespace Jield\Authorize\Factory;

use Jield\Authorize\Config;
use Jield\Authorize\View\UnauthorizedStrategy;
use Jield\Stdlib\Service;
use Jield\Stdlib\Value;
use Laminas\Authentication\AuthenticationService;
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
        $config = Value::array($container->get(Config::class));
        $strategy = new UnauthorizedStrategy(
            authenticationService: Service::get(
                $container,
                AuthenticationService::class,
            ),
            config: $config,
        );
        $strategy->setTemplate(Value::string($config['template']));
        return $strategy;
    }
}
