<?php

namespace Jield\Mvc\Factory;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class ViewJsonStrategyFactory implements FactoryInterface
{
    /**
     * Create and return the JSON view strategy
     *
     * Retrieves the ViewJsonRenderer service from the service locator, and
     * injects it into the constructor for the JSON strategy.
     *
     * It then attaches the strategy to the View service, at a priority of 100.
     *
     * @param  string $requestedName
     * @param  null|array $options
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null)
    {
        if (!class_exists(\Laminas\View\Strategy\JsonStrategy::class)) {
            throw new ServiceNotCreatedException(
                'Laminas\\View\\Strategy\\JsonStrategy is not available in laminas-view v3; '
                . 'install laminas/laminas-mvc or provide a compatible strategy.'
            );
        }

        $jsonRenderer = $container->get('ViewJsonRenderer');
        $strategyClass = \Laminas\View\Strategy\JsonStrategy::class;
        return new $strategyClass($jsonRenderer);
    }
}
