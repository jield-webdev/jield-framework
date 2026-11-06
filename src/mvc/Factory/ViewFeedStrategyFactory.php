<?php

declare(strict_types=1);

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
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): mixed
    {
        $strategyClass = 'Laminas\\View\\Strategy\\FeedStrategy';
        if (!class_exists($strategyClass)) {
            throw new ServiceNotCreatedException(
                'Laminas\\View\\Strategy\\FeedStrategy is not available in laminas-view v3; '
                . 'install laminas/laminas-mvc or provide a compatible strategy.',
            );
        }

        /** @var mixed $renderer */
        $renderer = $container->get('ViewFeedRenderer');
        return \Jield\Stdlib\Value::instantiate($strategyClass, [$renderer]);
    }
}
