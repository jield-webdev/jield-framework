<?php

declare(strict_types=1);

namespace Jield\GoogleAnalytics;

use Jield\GoogleAnalytics\View\Helper\GoogleAnalytics;
use Jield\ModuleManager\Feature\BootstrapListenerInterface;
use Jield\ModuleManager\Feature\ConfigProviderInterface;
use Jield\Mvc\ApplicationInterface;
use Jield\Mvc\MvcEvent;
use Jield\Stdlib\Service;
use Laminas\EventManager\EventInterface;
use Laminas\Http\Request as HttpRequest;
use Laminas\View\HelperPluginManager;
use Override;

final class Module implements ConfigProviderInterface, BootstrapListenerInterface
{
    #[Override]
    public function getConfig(): array
    {
        /** @var array<array-key, mixed> $config */
        $config = include __DIR__ . '/../../resources/google-analytics/config/module.config.php';
        return \Webmozart\Assert\Assert::isArray($config);
    }

    #[Override]
    public function onBootstrap(EventInterface $e): void
    {
        /** @var ApplicationInterface $app */
        $app = $e->getParam(name: 'application');

        if (!$app->getRequest() instanceof HttpRequest) {
            return;
        }

        $sm = $app->getServiceManager();
        $em = $app->getEventManager();

        $em->attach(
            MvcEvent::EVENT_RENDER,
            /** @throws \Psr\Container\ContainerExceptionInterface */ static function (MvcEvent $e) use ($sm): void {
                $view = Service::get($sm, HelperPluginManager::class, 'ViewHelperManager');
                /** @var GoogleAnalytics $plugin */
                $plugin = $view->get('googleAnalytics');
                $plugin->appendScript();
            },
        );
    }
}
