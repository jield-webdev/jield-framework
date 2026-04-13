<?php

namespace Jield\AssetManager;

use Jield\AssetManager\Service\AssetManager;
use Jield\ModuleManager\Feature\BootstrapListenerInterface;
use Jield\ModuleManager\Feature\ConfigProviderInterface;
use Jield\Mvc\MvcEvent;
use Laminas\EventManager\EventInterface;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Http\Response;
use Override;


class Module implements
    ConfigProviderInterface,
    BootstrapListenerInterface
{
    /**
     * {@inheritDoc}
     */
    #[Override]
    public function getConfig(): array
    {
        return include __DIR__ . '/../../config/asset-manager/module.config.php';
    }

    /**
     * Callback method for dispatch and dispatch.error events.
     *
     * @param MvcEvent $event
     */
    public function onDispatch(MvcEvent $event): ?Response
    {
        /** @var Response $response */
        $response = $event->getResponse();

        if (!method_exists(object_or_class: $response, method: 'getStatusCode') || $response->getStatusCode() !== 404) {
            return null;
        }

        $request        = $event->getRequest();
        $serviceManager = $event->getApplication()->getServiceManager();
        /** @var AssetManager $assetManager */
        $assetManager = $serviceManager->get(AssetManager::class);

        if (!$assetManager->resolvesToAsset(request: $request)) {
            return null;
        }

        $response->setStatusCode(code: 200);

        return $assetManager->setAssetOnResponse(response: $response);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function onBootstrap(EventInterface $e): void
    {
        // Attach for dispatch, and dispatch.error (with low priority to make sure statusCode gets set)
        /* @var $eventManager EventManagerInterface */
        /** @phpstan-ignore-next-line */
        $eventManager = $e->getTarget()->getEventManager();
        $callback     = $this->onDispatch(...);
        $priority     = -9999999;
        $eventManager->attach(eventName: MvcEvent::EVENT_DISPATCH, listener: $callback, priority: $priority);
        $eventManager->attach(eventName: MvcEvent::EVENT_DISPATCH_ERROR, listener: $callback, priority: $priority);
    }
}
