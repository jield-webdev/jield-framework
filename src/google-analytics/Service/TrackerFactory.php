<?php

declare(strict_types=1);

namespace Jield\GoogleAnalytics\Service;

use Jield\GoogleAnalytics\Analytics\Tracker;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;

final class TrackerFactory implements FactoryInterface
{
    #[Override]
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Tracker
    {
        /** @var array{google_analytics: array{id?: string, enable: bool}} $config */
        $config = $container->get('config');
        $gaConfig = $config['google_analytics'];

        return new Tracker(id: $gaConfig['id'] ?? '', enableTracking: $gaConfig['enable']);
    }
}
