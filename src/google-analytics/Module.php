<?php

declare(strict_types=1);

namespace Jield\GoogleAnalytics;

use Jield\GoogleAnalytics\View\Helper\GoogleAnalytics;
use Jield\ModuleManager\Feature\BootstrapListenerInterface;
use Jield\ModuleManager\Feature\ConfigProviderInterface;
use Jield\Mvc\MvcEvent;
use Laminas\EventManager\EventInterface;
use Laminas\Http\Request as HttpRequest;
use Override;

final class Module implements ConfigProviderInterface, BootstrapListenerInterface
{
    #[Override]
    public function getConfig(): array
    {
        /** @var array<array-key, mixed> $config */
        $config = include __DIR__ . '/../../resources/google-analytics/config/module.config.php';
        return $config;
    }

    #[Override]
    public function onBootstrap(EventInterface $e): void
    {
        /** @var \Jield\Mvc\ApplicationInterface $app */
        $app = $e->getParam(name: 'application');

        if (!$app->getRequest() instanceof HttpRequest) {
            return;
        }

        $sm = $app->getServiceManager();
        $em = $app->getEventManager();

        $em->attach(MvcEvent::EVENT_RENDER, static function (MvcEvent $e) use ($sm): void {
            $view = \Jield\Stdlib\Service::get($sm, \Laminas\View\HelperPluginManager::class, 'ViewHelperManager');
            /** @var GoogleAnalytics $plugin */
            $plugin = $view->get('googleAnalytics');
            $plugin->appendScript();
        });
    }
}
