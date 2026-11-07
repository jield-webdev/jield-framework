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
use Jield\Mvc\SendResponseListener;
use Jield\Mvc\View\Http\JsonStrategy;
use Jield\Mvc\View\Model\JsonModel;
use Jield\Stdlib\Service;
use Jield\Stdlib\Value;
use Laminas\Authentication\AuthenticationService;
use Laminas\Authentication\AuthenticationServiceInterface;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\EventManager\EventInterface;
use Laminas\EventManager\EventManager;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Http\Request as HttpRequest;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\DispatchableInterface;
use Override;
use Webmozart\Assert\Assert;

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
        $this->getPrototypeRouteListener()->attach(events: Value::call($moduleManager, 'getEventManager'));
    }

    #[Override]
    public function onBootstrap(EventInterface|MvcEvent $e): void
    {
        $app = $e->getParam(name: 'application');

        /** @var ServiceManager $serviceManager */
        $serviceManager = Value::call($app, 'getServiceManager');
        /** @var EventManager $eventManager */
        $eventManager = Value::call($app, 'getEventManager');

        Service::get($serviceManager, \Jield\ApiTools\Versioning\AcceptListener::class)->attach($eventManager);
        Service::get($serviceManager, \Jield\ApiTools\Versioning\ContentTypeListener::class)->attach($eventManager);
        Service::get($serviceManager, VersionListener::class)->attach($eventManager);

        //ApiProblem
        Service::get($serviceManager, ApiProblemListener::class)->attach($eventManager);
        Service::get($serviceManager, ApiProblemStrategy::class)->attach($eventManager, 400);
        Service::get($serviceManager, HalJsonStrategy::class)->attach($eventManager, 300);

        $sendResponseListener = Service::get($serviceManager, SendResponseListener::class, 'SendResponseListener');
        $sendResponseListener->getEventManager()->attach(
            SendResponseEvent::EVENT_SEND_RESPONSE,
            Service::get($serviceManager, SendApiProblemResponseListener::class),
            -500,
        );

        $eventManager->attach(
            eventName: MvcEvent::EVENT_ROUTE,
            listener: Service::get($serviceManager, ContentTypeListener::class),
            priority: -625,
        );

        Service::get($serviceManager, AcceptFilterListener::class)->attach($eventManager);
        Service::get($serviceManager, ContentTypeFilterListener::class)->attach($eventManager);

        $contentNegotiationOptions = Service::get($serviceManager, ContentNegotiationOptions::class);
        if ($contentNegotiationOptions->getXHttpMethodOverrideEnabled()) {
            Service::get($serviceManager, HttpMethodOverrideListener::class)->attach($eventManager);
        }

        $sharedEventManager = $eventManager->getSharedManager();
        Assert::notNull($sharedEventManager)->attach(
            DispatchableInterface::class,
            MvcEvent::EVENT_DISPATCH,
            listener: Service::get($serviceManager, AcceptListener::class),
            priority: -10,
        );

        $eventManager->attach(
            eventName: MvcAuthEvent::EVENT_AUTHENTICATION_POST,
            listener: Service::get($serviceManager, MvcAuth\UnauthenticatedListener::class),
            priority: 100,
        );
        $eventManager->attach(
            eventName: MvcAuthEvent::EVENT_AUTHORIZATION_POST,
            listener: Service::get($serviceManager, MvcAuth\UnauthorizedListener::class),
            priority: 100,
        );
        $eventManager->attach(eventName: MvcEvent::EVENT_RENDER, listener: $this->onRenderMain(...), priority: 400);

        Service::get($serviceManager, ContentValidationListener::class)->attach($eventManager);

        if ($e instanceof MvcEvent && $e->getRequest() instanceof HttpRequest) {
            $authentication = Service::get($serviceManager, AuthenticationServiceInterface::class, 'authentication');
            $mvcAuthEvent = new MvcAuthEvent(
                mvcEvent: $e,
                authentication: $authentication,
                authorization: $serviceManager->get('authorization'),
            );

            new MvcRouteListener(
                mvcAuthEvent: $mvcAuthEvent,
                events: $eventManager,
                authentication: Assert::isInstanceOf($authentication, AuthenticationService::class),
            );

            $eventManager->attach(eventName: MvcAuthEvent::EVENT_AUTHENTICATION, listener: Service::get(
                $serviceManager,
                DefaultAuthenticationListener::class,
            ));
            $eventManager->attach(eventName: MvcAuthEvent::EVENT_AUTHENTICATION_POST, listener: Service::get(
                $serviceManager,
                DefaultAuthenticationPostListener::class,
            ));
            $eventManager->attach(
                eventName: MvcAuthEvent::EVENT_AUTHORIZATION,
                listener: Service::get($serviceManager, DefaultResourceResolverListener::class),
                priority: 1000,
            );
            $eventManager->attach(eventName: MvcAuthEvent::EVENT_AUTHORIZATION, listener: Service::get(
                $serviceManager,
                DefaultAuthorizationListener::class,
            ));
            $eventManager->attach(eventName: MvcAuthEvent::EVENT_AUTHORIZATION_POST, listener: Service::get(
                $serviceManager,
                DefaultAuthorizationPostListener::class,
            ));
        }

        Service::get($serviceManager, \Jield\ApiTools\Rest\Listener\OptionsListener::class)->attach($eventManager);
        Service::get($serviceManager, RestParametersListener::class)->attachShared(Assert::isInstanceOf(
            $sharedEventManager,
            SharedEventManagerInterface::class,
        ));

        // Attach OptionsListener
        $optionsListener = Service::get($serviceManager, OptionsListener::class);
        $optionsListener->attach(Value::call($app, 'getEventManager'));

        // Setup json strategy
        $strategy = Service::get($serviceManager, JsonStrategy::class, 'ViewJsonStrategy');
        $strategy->attach($eventManager, 100);
    }

    public function onRenderMain(MvcEvent $e): void
    {
        $result = $e->getResult();
        if (!$result instanceof JsonModel) {
            return;
        }

        $app = $e->getApplication();
        $services = Assert::notNull($app)->getServiceManager();
        $events = Assert::notNull($app)->getEventManager();
        Service::get($services, RenderErrorListener::class)->attach($events);
    }

    public function onRenderApiProblem(MvcEvent $e): void
    {
        $app = $e->getApplication();
        $services = Assert::notNull($app)->getServiceManager();

        if ($services->has('View')) {
            $view = Service::get($services, \Laminas\View\View::class, 'View');
            $events = Assert::notNull($app)->getEventManager();

            // register at high priority, to "beat" normal json strategy registered
            // via view manager, as well as HAL strategy.
            Service::get($services, ApiProblemStrategy::class)->attach($events, 400);
        }
    }

    public function onRenderHal(MvcEvent $e): void
    {
        $result = $e->getResult();
        if (!$result instanceof HalJsonModel) {
            return;
        }

        /** @var ApplicationInterface $application */
        $application = $e->getTarget();
        $services = $application->getServiceManager();

        $view = Service::get($services, \Laminas\View\View::class, 'View');
        $events = $application->getEventManager();

        // register at high priority, to "beat" normal json strategy registered
        // via view manager

        $halStrategy = Service::get($services, HalJsonStrategy::class);
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
