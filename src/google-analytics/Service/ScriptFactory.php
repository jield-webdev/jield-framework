<?php

declare(strict_types=1);

namespace Jield\GoogleAnalytics\Service;

use Jield\GoogleAnalytics\Analytics\Tracker;
use Jield\GoogleAnalytics\View\Helper\Script\Gajs;
use Jield\GoogleAnalytics\View\Helper\Script\ScriptInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;

final class ScriptFactory implements FactoryInterface
{
    #[Override]
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): ScriptInterface
    {
        //We need to use the headscript, this is a required of the Google Tag
        //Copy the global site tag into the <head> section of your HTML

        $script = \Jield\Stdlib\Service::get($container, Gajs::class);

        /* @var $ga Tracker */
        $ga = \Jield\Stdlib\Service::get($container, Tracker::class);

        $script->setTracker(tracker: $ga);

        return $script;
    }
}
