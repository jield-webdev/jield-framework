<?php

namespace Jield\Mvc\Service;

// phpcs:ignore
use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\View\Strategy\FeedStrategy;

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
     * @return FeedStrategy
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): mixed
    {
        return new FeedStrategy($container->get('ViewFeedRenderer'));
    }
}
