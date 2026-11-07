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
use Webmozart\Assert\Assert;

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
     * @throws Exception\RuntimeException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \ReflectionException
     */
    public function onDispatch(MvcEvent $event): ?Response
    {
        $response = $event->getResponse();

        if (!$response instanceof Response || $response->getStatusCode() !== 404) {
            return null;
        }

        $request = $event->getRequest();
        $serviceManager = Assert::notNull($event->getApplication())->getServiceManager();

        $assetManager = Service::get($serviceManager, AssetManager::class);

        if (!$assetManager->resolvesToAsset(request: $request)) {
            return null;
        }

        $response->setStatusCode(code: 200);

        return $assetManager->setAssetOnResponse(response: $response);
    }

    /**
     * {@inheritDoc}
     * @throws \ReflectionException
     */
    #[Override]
    public function onBootstrap(EventInterface $e): void
    {
        // Attach for dispatch, and dispatch.error (with low priority to make sure statusCode gets set)
        /* @var $eventManager EventManagerInterface
         * @var mixed $eventManager
         */
        /** @mago-var mixed $eventManager */
        $eventManager = Value::call(Assert::object($e->getTarget()), 'getEventManager');
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
