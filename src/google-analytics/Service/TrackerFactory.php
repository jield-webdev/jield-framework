<?php

declare(strict_types=1);

namespace Jield\GoogleAnalytics\Service;

use Jield\GoogleAnalytics\Analytics\Tracker;
use Jield\Stdlib\Value;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;

final class TrackerFactory implements FactoryInterface
{
    #[Override]
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Tracker
    {
        /** @var array{google_analytics: array{id?: string|false, enable: bool}} $config */
        $config = $container->get('config');
        $gaConfig = $config['google_analytics'];

        $id = Value::stringify($gaConfig['id'] ?? '');
        return new Tracker(id: $id, enableTracking: $gaConfig['enable'] && $id !== '');
    }
}
