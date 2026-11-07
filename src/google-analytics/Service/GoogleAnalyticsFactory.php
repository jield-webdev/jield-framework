<?php

declare(strict_types=1);

namespace Jield\GoogleAnalytics\Service;

use Jield\GoogleAnalytics\Analytics\Tracker;
use Jield\GoogleAnalytics\View\Helper\GoogleAnalytics;
use Jield\GoogleAnalytics\View\Helper\Script\ScriptInterface;
use Jield\Stdlib\Service;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;

final class GoogleAnalyticsFactory implements FactoryInterface
{
    #[Override]
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): GoogleAnalytics
    {
        $tracker = Service::get($container, Tracker::class);

        $script = Service::get(
            $container,
            ScriptInterface::class,
            ScriptFactory::class,
        );
        return new GoogleAnalytics(script: $script, id: $tracker->getId());
    }
}
