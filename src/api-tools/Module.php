<?php

declare(strict_types=1);

namespace Jield\ApiTools;

use Jield\ApiTools\ApiProblem\Listener\ApiProblemListener;
use Jield\ApiTools\ApiProblem\Listener\RenderErrorListener;
use Jield\ApiTools\ApiProblem\Listener\SendApiProblemResponseListener;
use Jield\ApiTools\ApiProblem\View\ApiProblemStrategy;
use Jield\ApiTools\ContentNegotiation\AcceptFilterListener;
use Jield\ApiTools\ContentNegotiation\AcceptListener;
use Jield\ApiTools\ContentNegotiation\ContentNegotiationOptions;
use Jield\ApiTools\ContentNegotiation\ContentTypeFilterListener;
use Jield\ApiTools\ContentNegotiation\ContentTypeListener;
use Jield\ApiTools\ContentNegotiation\HttpMethodOverrideListener;
use Jield\ApiTools\ContentValidation\ContentValidationListener;
use Jield\ApiTools\Hal\View\HalJsonModel;
use Jield\ApiTools\Hal\View\HalJsonStrategy;
use Jield\ApiTools\MvcAuth\Authentication\DefaultAuthenticationListener;
use Jield\ApiTools\MvcAuth\Authentication\DefaultAuthenticationPostListener;
use Jield\ApiTools\MvcAuth\Authorization\DefaultAuthorizationListener;
use Jield\ApiTools\MvcAuth\Authorization\DefaultAuthorizationPostListener;
use Jield\ApiTools\MvcAuth\Authorization\DefaultResourceResolverListener;
use Jield\ApiTools\MvcAuth\MvcAuthEvent;
use Jield\ApiTools\MvcAuth\MvcRouteListener;
use Jield\ApiTools\Rest\Listener\RestParametersListener;
use Jield\ApiTools\Rpc\OptionsListener;
use Jield\ApiTools\Versioning\PrototypeRouteListener;
use Jield\ApiTools\Versioning\VersionListener;
use Jield\ModuleManager\Feature\BootstrapListenerInterface;
use Jield\ModuleManager\Feature\ConfigProviderInterface;
use Jield\Mvc\ApplicationInterface;
use Jield\Mvc\MvcEvent;
use Jield\Mvc\ResponseSender\SendResponseEvent;
use Jield\Mvc\View\Model\JsonModel;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\EventManager\EventInterface;
use Laminas\EventManager\EventManager;
use Laminas\Http\Request as HttpRequest;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\DispatchableInterface;
use Laminas\View\View;
use Override;

final class Module implements ConfigProviderInterface, BootstrapListenerInterface
{
    private ?PrototypeRouteListener $prototypeRouteListener = null;

    #[Override]
    public function getConfig(): array
    {
        return new ConfigAggregator(providers: [
            ConfigProvider::class,
            SettingsProvider::class,
        ])->getMergedConfig();
    }

    public function init($moduleManager): void
    {
        $this->getPrototypeRouteListener()->attach(events: \Jield\Stdlib\Value::instance(\Jield\Stdlib\Value::call($moduleManager, 'getEventManager'), \Laminas\EventManager\EventManagerInterface::class));
    }

    #[Override]
    public function onBootstrap(EventInterface|MvcEvent $e): void
    {
        /** @var mixed $app */
        $app = $e->getParam(name: 'application');

        /** @var ServiceManager $serviceManager */
        $serviceManager = \Jield\Stdlib\Value::call($app, 'getServiceManager');
        /** @var EventManager $eventManager */
        $eventManager = \Jield\Stdlib\Value::call($app, 'getEventManager');

        \Jield\Stdlib\Service::get($serviceManager, \Jield\ApiTools\Versioning\AcceptListener::class)->attach($eventManager);
        \Jield\Stdlib\Service::get($serviceManager, \Jield\ApiTools\Versioning\ContentTypeListener::class)->attach($eventManager);
        \Jield\Stdlib\Service::get($serviceManager, VersionListener::class)->attach($eventManager);

        //ApiProblem
        \Jield\Stdlib\Service::get($serviceManager, ApiProblemListener::class)->attach($eventManager);
        \Jield\Stdlib\Service::get($serviceManager, ApiProblemStrategy::class)->attach($eventManager, 400);
        \Jield\Stdlib\Service::get($serviceManager, HalJsonStrategy::class)->attach($eventManager, 300);

        $sendResponseListener = \Jield\Stdlib\Service::get($serviceManager, \Jield\Mvc\SendResponseListener::class, 'SendResponseListener');
        $sendResponseListener->getEventManager()->attach(
            SendResponseEvent::EVENT_SEND_RESPONSE,
            \Jield\Stdlib\Service::get($serviceManager, SendApiProblemResponseListener::class),
            -500,
        );

        $eventManager->attach(
            eventName: MvcEvent::EVENT_ROUTE,
            listener: \Jield\Stdlib\Service::get($serviceManager, ContentTypeListener::class),
            priority: -625,
        );

        \Jield\Stdlib\Service::get($serviceManager, AcceptFilterListener::class)->attach($eventManager);
        \Jield\Stdlib\Service::get($serviceManager, ContentTypeFilterListener::class)->attach($eventManager);

        $contentNegotiationOptions = \Jield\Stdlib\Service::get($serviceManager, ContentNegotiationOptions::class);
        if ($contentNegotiationOptions->getXHttpMethodOverrideEnabled()) {
            \Jield\Stdlib\Service::get($serviceManager, HttpMethodOverrideListener::class)->attach($eventManager);
        }

        $sharedEventManager = $eventManager->getSharedManager();
        \Jield\Stdlib\Value::present($sharedEventManager)->attach(
            DispatchableInterface::class,
            MvcEvent::EVENT_DISPATCH,
            listener: \Jield\Stdlib\Service::get($serviceManager, AcceptListener::class),
            priority: -10,
        );

        $eventManager->attach(
            eventName: MvcAuthEvent::EVENT_AUTHENTICATION_POST,
            listener: \Jield\Stdlib\Service::get($serviceManager, MvcAuth\UnauthenticatedListener::class),
            priority: 100,
        );
        $eventManager->attach(
            eventName: MvcAuthEvent::EVENT_AUTHORIZATION_POST,
            listener: \Jield\Stdlib\Service::get($serviceManager, MvcAuth\UnauthorizedListener::class),
            priority: 100,
        );
        $eventManager->attach(eventName: MvcEvent::EVENT_RENDER, listener: $this->onRenderMain(...), priority: 400);

        \Jield\Stdlib\Service::get($serviceManager, ContentValidationListener::class)->attach($eventManager);

        if ($e instanceof MvcEvent && $e->getRequest() instanceof HttpRequest) {
            $authentication = \Jield\Stdlib\Service::get($serviceManager, \Laminas\Authentication\AuthenticationServiceInterface::class, 'authentication');
            $mvcAuthEvent = new MvcAuthEvent(
                mvcEvent: $e,
                authentication: $authentication,
                authorization: $serviceManager->get('authorization'),
            );

            new MvcRouteListener(mvcAuthEvent: $mvcAuthEvent, events: $eventManager, authentication: \Jield\Stdlib\Value::instance($authentication, \Laminas\Authentication\AuthenticationService::class));

            $eventManager->attach(
                eventName: MvcAuthEvent::EVENT_AUTHENTICATION,
                listener: \Jield\Stdlib\Service::get($serviceManager, DefaultAuthenticationListener::class),
            );
            $eventManager->attach(
                eventName: MvcAuthEvent::EVENT_AUTHENTICATION_POST,
                listener: \Jield\Stdlib\Service::get($serviceManager, DefaultAuthenticationPostListener::class),
            );
            $eventManager->attach(
                eventName: MvcAuthEvent::EVENT_AUTHORIZATION,
                listener: \Jield\Stdlib\Service::get($serviceManager, DefaultResourceResolverListener::class),
                priority: 1000,
            );
            $eventManager->attach(
                eventName: MvcAuthEvent::EVENT_AUTHORIZATION,
                listener: \Jield\Stdlib\Service::get($serviceManager, DefaultAuthorizationListener::class),
            );
            $eventManager->attach(
                eventName: MvcAuthEvent::EVENT_AUTHORIZATION_POST,
                listener: \Jield\Stdlib\Service::get($serviceManager, DefaultAuthorizationPostListener::class),
            );
        }

        \Jield\Stdlib\Service::get($serviceManager, \Jield\ApiTools\Rest\Listener\OptionsListener::class)->attach($eventManager);
        \Jield\Stdlib\Service::get($serviceManager, RestParametersListener::class)->attachShared(\Jield\Stdlib\Value::instance($sharedEventManager, \Laminas\EventManager\SharedEventManagerInterface::class));

        // Attach OptionsListener
        $optionsListener = \Jield\Stdlib\Service::get($serviceManager, OptionsListener::class);
        $optionsListener->attach(\Jield\Stdlib\Value::instance(\Jield\Stdlib\Value::call($app, 'getEventManager'), \Laminas\EventManager\EventManagerInterface::class));

        // Setup json strategy
        $strategy = \Jield\Stdlib\Service::get($serviceManager, \Jield\Mvc\View\Http\JsonStrategy::class, 'ViewJsonStrategy');
        $strategy->attach($eventManager, 100);
    }

    public function onRenderMain(MvcEvent $e): void
    {
        /** @var mixed $result */
        $result = $e->getResult();
        if (!$result instanceof JsonModel) {
            return;
        }

        $app = $e->getApplication();
        $services = \Jield\Stdlib\Value::present($app)->getServiceManager();
        $events = \Jield\Stdlib\Value::present($app)->getEventManager();
        \Jield\Stdlib\Service::get($services, RenderErrorListener::class)->attach($events);
    }

    public function onRenderApiProblem(MvcEvent $e): void
    {
        $app = $e->getApplication();
        $services = \Jield\Stdlib\Value::present($app)->getServiceManager();

        if ($services->has('View')) {
            $view = \Jield\Stdlib\Service::get($services, \Laminas\View\View::class, 'View');
            $events = \Jield\Stdlib\Value::present($app)->getEventManager();

            // register at high priority, to "beat" normal json strategy registered
            // via view manager, as well as HAL strategy.
            \Jield\Stdlib\Service::get($services, ApiProblemStrategy::class)->attach($events, 400);
        }
    }

    public function onRenderHal(MvcEvent $e): void
    {
        /** @var mixed $result */
        $result = $e->getResult();
        if (!$result instanceof HalJsonModel) {
            return;
        }

        /** @var ApplicationInterface $application */
        $application = $e->getTarget();
        $services = $application->getServiceManager();
        
        $view = \Jield\Stdlib\Service::get($services, \Laminas\View\View::class, 'View');
        $events = $application->getEventManager();

        // register at high priority, to "beat" normal json strategy registered
        // via view manager
        
        $halStrategy = \Jield\Stdlib\Service::get($services, HalJsonStrategy::class);
        $halStrategy->attach(events: $events, priority: 200);
    }

    public function getPrototypeRouteListener(): PrototypeRouteListener
    {
        if ($this->prototypeRouteListener instanceof \Jield\ApiTools\Versioning\PrototypeRouteListener) {
            return $this->prototypeRouteListener;
        }

        $this->prototypeRouteListener = new \Jield\ApiTools\Versioning\PrototypeRouteListener();
        return $this->prototypeRouteListener;
    }
}
