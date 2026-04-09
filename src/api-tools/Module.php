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
use Jield\ConfigAggregator\ConfigAggregator;
use Laminas\EventManager\EventInterface;
use Laminas\EventManager\EventManager;
use Jield\Http\Request as HttpRequest;
use Jield\ModuleManager\Feature\BootstrapListenerInterface;
use Jield\ModuleManager\Feature\ConfigProviderInterface;
use Jield\Mvc\ApplicationInterface;
use Jield\Mvc\MvcEvent;
use Jield\Mvc\ResponseSender\SendResponseEvent;
use Laminas\ServiceManager\ServiceManager;
use Jield\Stdlib\DispatchableInterface;
use Laminas\View\Model\JsonModel;
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
            ListenerConfigProvider::class,
            SettingsProvider::class,
        ])->getMergedConfig();
    }

    public function init($moduleManager): void
    {
        $this->getPrototypeRouteListener()->attach(events: $moduleManager->getEventManager());
    }

    #[Override]
    public function onBootstrap(EventInterface|MvcEvent $e): void
    {
        $app = $e->getParam(name: 'application');

        /** @var ServiceManager $serviceManager */
        $serviceManager = $app->getServiceManager();
        /** @var EventManager $eventManager */
        $eventManager = $app->getEventManager();

        $serviceManager->get(name: \Jield\ApiTools\Versioning\AcceptListener::class)->attach($eventManager);
        $serviceManager->get(name: \Jield\ApiTools\Versioning\ContentTypeListener::class)->attach($eventManager);
        $serviceManager->get(name: VersionListener::class)->attach($eventManager);

        //ApiProblem
        $serviceManager->get(name: ApiProblemListener::class)->attach($eventManager);
        $eventManager->attach(eventName: MvcEvent::EVENT_RENDER, listener: $this->onRenderApiProblem(...), priority: 100);
        $eventManager->attach(eventName: MvcEvent::EVENT_RENDER, listener: $this->onRenderHal(...), priority: 100);

        $sendResponseListener = $serviceManager->get(name: 'SendResponseListener');
        $sendResponseListener->getEventManager()->attach(
            SendResponseEvent::EVENT_SEND_RESPONSE,
            $serviceManager->get(name: SendApiProblemResponseListener::class),
            -500
        );

        $eventManager->attach(eventName: MvcEvent::EVENT_ROUTE, listener: $serviceManager->get(name: ContentTypeListener::class), priority: -625);

        $serviceManager->get(name: AcceptFilterListener::class)->attach($eventManager);
        $serviceManager->get(name: ContentTypeFilterListener::class)->attach($eventManager);

        $contentNegotiationOptions = $serviceManager->get(name: ContentNegotiationOptions::class);
        if ($contentNegotiationOptions->getXHttpMethodOverrideEnabled()) {
            $serviceManager->get(name: HttpMethodOverrideListener::class)->attach($eventManager);
        }

        $sharedEventManager = $eventManager->getSharedManager();
        $sharedEventManager->attach(
            DispatchableInterface::class,
            MvcEvent::EVENT_DISPATCH,
            listener: $serviceManager->get(name: AcceptListener::class),
            priority: -10
        );

        $eventManager->attach(
            eventName: MvcAuthEvent::EVENT_AUTHENTICATION_POST,
            listener: $serviceManager->get(name: MvcAuth\UnauthenticatedListener::class),
            priority: 100
        );
        $eventManager->attach(
            eventName: MvcAuthEvent::EVENT_AUTHORIZATION_POST,
            listener: $serviceManager->get(name: MvcAuth\UnauthorizedListener::class),
            priority: 100
        );
        $eventManager->attach(eventName: MvcEvent::EVENT_RENDER, listener: $this->onRenderMain(...), priority: 400);

        $serviceManager->get(name: ContentValidationListener::class)->attach($eventManager);

        if ($e instanceof MvcEvent && $e->getRequest() instanceof HttpRequest) {
            $authentication = $serviceManager->get(name: 'authentication');
            $mvcAuthEvent   = new MvcAuthEvent(
                mvcEvent: $e,
                authentication: $authentication,
                authorization: $serviceManager->get(name: 'authorization')
            );

            new MvcRouteListener(mvcAuthEvent: $mvcAuthEvent, events: $eventManager, authentication: $authentication);

            $eventManager->attach(
                eventName: MvcAuthEvent::EVENT_AUTHENTICATION,
                listener: $serviceManager->get(name: DefaultAuthenticationListener::class)
            );
            $eventManager->attach(
                eventName: MvcAuthEvent::EVENT_AUTHENTICATION_POST,
                listener: $serviceManager->get(name: DefaultAuthenticationPostListener::class)
            );
            $eventManager->attach(
                eventName: MvcAuthEvent::EVENT_AUTHORIZATION,
                listener: $serviceManager->get(name: DefaultResourceResolverListener::class),
                priority: 1000
            );
            $eventManager->attach(
                eventName: MvcAuthEvent::EVENT_AUTHORIZATION,
                listener: $serviceManager->get(name: DefaultAuthorizationListener::class)
            );
            $eventManager->attach(
                eventName: MvcAuthEvent::EVENT_AUTHORIZATION_POST,
                listener: $serviceManager->get(name: DefaultAuthorizationPostListener::class)
            );
        }

        $serviceManager->get(name: \Jield\ApiTools\Rest\Listener\OptionsListener::class)->attach($eventManager);
        $serviceManager->get(name: RestParametersListener::class)->attachShared($sharedEventManager);

        // Attach OptionsListener
        $optionsListener = $serviceManager->get(name: OptionsListener::class);
        $optionsListener->attach($app->getEventManager());

        // Setup json strategy
        $strategy = $serviceManager->get(name: 'ViewJsonStrategy');
        $view     = $serviceManager->get(name: 'ViewManager')->getView();
        $strategy->attach($view->getEventManager(), 100);


    }

    public function onRenderMain(MvcEvent $e): void
    {
        $result = $e->getResult();
        if (!$result instanceof JsonModel) {
            return;
        }

        $app      = $e->getApplication();
        $services = $app->getServiceManager();
        $events   = $app->getEventManager();
        $services->get(RenderErrorListener::class)->attach(events: $events);
    }

    public function onRenderApiProblem(MvcEvent $e): void
    {
        $app      = $e->getApplication();
        $services = $app->getServiceManager();

        if ($services->has(id: 'View')) {
            $view   = $services->get('View');
            $events = $view->getEventManager();

            // register at high priority, to "beat" normal json strategy registered
            // via view manager, as well as HAL strategy.
            $services->get(ApiProblemStrategy::class)->attach($events, 400);
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
        $services    = $application->getServiceManager();
        /** @var View $view */
        $view   = $services->get('View');
        $events = $view->getEventManager();

        // register at high priority, to "beat" normal json strategy registered
        // via view manager
        /** @var HalJsonStrategy $halStrategy */
        $halStrategy = $services->get(HalJsonStrategy::class);
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
