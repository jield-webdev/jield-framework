<?php

namespace Jield\Mvc\Factory;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class ViewFeedStrategyFactory implements FactoryInterface
{
    /**
     * Create and return the Feed view strategy
     *
     * Retrieves the ViewFeedRenderer service from the service locator, and
     * injects it into the constructor for the feed strategy.
     *
     * It then attaches the strategy to the View service, at a priority of 100.
     *
     * @param  string $requestedName
     * @param  null|array $options
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null)
    {
        if (!class_exists(\Laminas\View\Strategy\FeedStrategy::class)) {
            throw new ServiceNotCreatedException(
                'Laminas\\View\\Strategy\\FeedStrategy is not available in laminas-view v3; '
                . 'install laminas/laminas-mvc or provide a compatible strategy.'
            );
        }

        $renderer      = $container->get('ViewFeedRenderer');
        $strategyClass = \Laminas\View\Strategy\FeedStrategy::class;
        return new $strategyClass($renderer);
    }
}
