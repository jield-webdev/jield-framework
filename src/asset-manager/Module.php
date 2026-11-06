<?php

declare(strict_types=1);

namespace Jield\AssetManager;

use Jield\AssetManager\Service\AssetManager;
use Jield\ModuleManager\Feature\BootstrapListenerInterface;
use Jield\ModuleManager\Feature\ConfigProviderInterface;
use Jield\Mvc\MvcEvent;
use Laminas\EventManager\EventInterface;
use Laminas\Http\Response;
use Override;

class Module implements ConfigProviderInterface, BootstrapListenerInterface
{
    /**
     * {@inheritDoc}
     */
    #[Override]
    public function getConfig(): array
    {
        /** @var array<array-key, mixed> $config */
        $config = include __DIR__ . '/../../config/asset-manager/module.config.php';
        return $config;
    }

    /**
     * Callback method for dispatch and dispatch.error events.
     *
     * @param MvcEvent $event
     */
    public function onDispatch(MvcEvent $event): ?Response
    {
        $response = $event->getResponse();

        if (!$response instanceof Response || $response->getStatusCode() !== 404) {
            return null;
        }

        $request = $event->getRequest();
        $serviceManager = \Jield\Stdlib\Value::present($event->getApplication())->getServiceManager();
        
        $assetManager = \Jield\Stdlib\Service::get($serviceManager, AssetManager::class);

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
        /* @var $eventManager EventManagerInterface 
 * @phpstan-ignore-next-line
         * @var mixed $eventManager
         */
        /** @var mixed $eventManager */
        $eventManager = \Jield\Stdlib\Value::call(\Jield\Stdlib\Value::present(\Jield\Stdlib\Value::object($e->getTarget())), 'getEventManager');
        $callback = $this->onDispatch(...);
        $priority = -9_999_999;
        \Jield\Stdlib\Value::call($eventManager, 'attach', ['eventName' => MvcEvent::EVENT_DISPATCH, 'listener' => $callback, 'priority' => $priority]);
        \Jield\Stdlib\Value::call($eventManager, 'attach', ['eventName' => MvcEvent::EVENT_DISPATCH_ERROR, 'listener' => $callback, 'priority' => $priority]);
    }
}
