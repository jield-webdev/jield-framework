<?php

declare(strict_types=1);

namespace Jield\AssetManager;

use Jield\AssetManager\Service\AssetManager;
use Jield\ModuleManager\Feature\BootstrapListenerInterface;
use Jield\ModuleManager\Feature\ConfigProviderInterface;
use Jield\Mvc\MvcEvent;
use Jield\Stdlib\Service;
use Jield\Stdlib\Value;
use Laminas\EventManager\EventInterface;
use Laminas\EventManager\EventManagerInterface;
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
        $serviceManager = Value::present($event->getApplication())->getServiceManager();

        $assetManager = Service::get($serviceManager, AssetManager::class);

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
         * @var mixed $eventManager
         */
        $eventManager = Value::call(
            Value::present(Value::object($e->getTarget())),
            'getEventManager',
        );
        $callback = $this->onDispatch(...);
        $priority = -9_999_999;
        Value::call($eventManager, 'attach', [
            'eventName' => MvcEvent::EVENT_DISPATCH,
            'listener' => $callback,
            'priority' => $priority,
        ]);
        Value::call($eventManager, 'attach', [
            'eventName' => MvcEvent::EVENT_DISPATCH_ERROR,
            'listener' => $callback,
            'priority' => $priority,
        ]);
    }
}
