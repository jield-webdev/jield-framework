<?php

declare(strict_types=1);

namespace Jield\ApiTools;

use Jield\ApiTools\ApiProblem\ApiProblem;
use Jield\ApiTools\ApiProblem\Listener\ApiProblemListener;
use Jield\ApiTools\ApiProblem\Listener\RenderErrorListener;
use Jield\ApiTools\ApiProblem\Listener\SendApiProblemResponseListener;
use Jield\ApiTools\ApiProblem\View\ApiProblemModel;
use Jield\ApiTools\ApiProblem\View\ApiProblemRenderer;
use Jield\ApiTools\ContentNegotiation\AcceptFilterListener;
use Jield\ApiTools\ContentNegotiation\AcceptListener;
use Jield\ApiTools\ContentNegotiation\ContentNegotiationOptions;
use Jield\ApiTools\ContentNegotiation\ContentTypeFilterListener;
use Jield\ApiTools\ContentNegotiation\ContentTypeListener;
use Jield\ApiTools\ContentNegotiation\HttpMethodOverrideListener;
use Jield\ApiTools\ContentNegotiation\JsonModel;
use Jield\ApiTools\ContentValidation\ContentValidationListener;
use Jield\ApiTools\Hal\View\HalJsonModel;
use Jield\ApiTools\Hal\View\HalJsonRenderer;
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
use Laminas\EventManager\EventInterface;
use Laminas\EventManager\EventManager;
use Laminas\Http\Request as HttpRequest;
use Laminas\Http\Response as HttpResponse;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\DispatchableInterface;
use Override;

final class Module implements ConfigProviderInterface, BootstrapListenerInterface
{
    private ?PrototypeRouteListener $prototypeRouteListener = null;

    #[Override]
    public function getConfig(): array
    {
        return new ConfigProvider()->__invoke();
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

        $serviceManager->get(id: \Jield\ApiTools\Versioning\AcceptListener::class)->attach($eventManager);
        $serviceManager->get(id: \Jield\ApiTools\Versioning\ContentTypeListener::class)->attach($eventManager);
        $serviceManager->get(id: VersionListener::class)->attach($eventManager);

        //ApiProblem
        $serviceManager->get(id: ApiProblemListener::class)->attach($eventManager);
        $eventManager->attach(eventName: MvcEvent::EVENT_RENDER, listener: $this->onRenderJson(...), priority: 100);

        $sendResponseListener = $serviceManager->get(id: 'SendResponseListener');
        $sendResponseListener->getEventManager()->attach(
            SendResponseEvent::EVENT_SEND_RESPONSE,
            $serviceManager->get(id: SendApiProblemResponseListener::class),
            -500
        );

        $eventManager->attach(eventName: MvcEvent::EVENT_ROUTE, listener: $serviceManager->get(id: ContentTypeListener::class), priority: -625);

        $serviceManager->get(id: AcceptFilterListener::class)->attach($eventManager);
        $serviceManager->get(id: ContentTypeFilterListener::class)->attach($eventManager);

        $contentNegotiationOptions = $serviceManager->get(id: ContentNegotiationOptions::class);
        if ($contentNegotiationOptions->getXHttpMethodOverrideEnabled()) {
            $serviceManager->get(id: HttpMethodOverrideListener::class)->attach($eventManager);
        }

        $sharedEventManager = $eventManager->getSharedManager();
        $sharedEventManager->attach(
            DispatchableInterface::class,
            MvcEvent::EVENT_DISPATCH,
            listener: $serviceManager->get(id: AcceptListener::class),
            priority: -10
        );

        $eventManager->attach(
            eventName: MvcAuthEvent::EVENT_AUTHENTICATION_POST,
            listener: $serviceManager->get(id: MvcAuth\UnauthenticatedListener::class),
            priority: 100
        );
        $eventManager->attach(
            eventName: MvcAuthEvent::EVENT_AUTHORIZATION_POST,
            listener: $serviceManager->get(id: MvcAuth\UnauthorizedListener::class),
            priority: 100
        );
        $eventManager->attach(eventName: MvcEvent::EVENT_RENDER, listener: $this->onRenderMain(...), priority: 400);

        $serviceManager->get(id: ContentValidationListener::class)->attach($eventManager);

        if ($e instanceof MvcEvent && $e->getRequest() instanceof HttpRequest) {
            $authentication = $serviceManager->get(id: 'authentication');
            $mvcAuthEvent   = new MvcAuthEvent(
                mvcEvent: $e,
                authentication: $authentication,
                authorization: $serviceManager->get(id: 'authorization')
            );

            new MvcRouteListener(mvcAuthEvent: $mvcAuthEvent, events: $eventManager, authentication: $authentication);

            $eventManager->attach(
                eventName: MvcAuthEvent::EVENT_AUTHENTICATION,
                listener: $serviceManager->get(id: DefaultAuthenticationListener::class)
            );
            $eventManager->attach(
                eventName: MvcAuthEvent::EVENT_AUTHENTICATION_POST,
                listener: $serviceManager->get(id: DefaultAuthenticationPostListener::class)
            );
            $eventManager->attach(
                eventName: MvcAuthEvent::EVENT_AUTHORIZATION,
                listener: $serviceManager->get(id: DefaultResourceResolverListener::class),
                priority: 1000
            );
            $eventManager->attach(
                eventName: MvcAuthEvent::EVENT_AUTHORIZATION,
                listener: $serviceManager->get(id: DefaultAuthorizationListener::class)
            );
            $eventManager->attach(
                eventName: MvcAuthEvent::EVENT_AUTHORIZATION_POST,
                listener: $serviceManager->get(id: DefaultAuthorizationPostListener::class)
            );
        }

        $serviceManager->get(id: \Jield\ApiTools\Rest\Listener\OptionsListener::class)->attach($eventManager);
        $serviceManager->get(id: RestParametersListener::class)->attachShared($sharedEventManager);

        // Attach OptionsListener
        $optionsListener = $serviceManager->get(id: OptionsListener::class);
        $optionsListener->attach($app->getEventManager());

    }

    public function onRenderMain(MvcEvent $e): void
    {
        $result = $e->getResult();
        if (
            !$result instanceof JsonModel
            && !$result instanceof HalJsonModel
            && !$result instanceof ApiProblemModel
        ) {
            return;
        }

        $app      = $e->getApplication();
        $services = $app->getServiceManager();
        $events   = $app->getEventManager();
        $services->get(RenderErrorListener::class)->attach(events: $events);
    }

    public function onRenderJson(MvcEvent $e): void
    {
        $result = $e->getResult();
        if (
            !$result instanceof JsonModel
            && !$result instanceof HalJsonModel
            && !$result instanceof ApiProblemModel
        ) {
            return;
        }

        $response = $e->getResponse();
        if (!$response instanceof HttpResponse) {
            return;
        }

        /** @var ApplicationInterface $application */
        $application = $e->getTarget();
        $services    = $application->getServiceManager();
        $headers     = $response->getHeaders();

        if ($result instanceof ApiProblemModel) {
            /** @var ApiProblemRenderer $renderer */
            $renderer = $services->get(ApiProblemRenderer::class);
            $content  = $renderer->render(nameOrModel: $result);

            $status = $result->getApiProblem()->getStatus();
            $status = $status >= 100 && $status < 600 ? $status : 500;

            $response->setStatusCode(code: $status);
            $response->setContent(value: $content);
            $headers->addHeaderLine(
                headerFieldNameOrLine: 'Content-Type',
                fieldValue: ApiProblem::CONTENT_TYPE
            );
            $e->setResult(result: $response);
            return;
        }

        if ($result instanceof HalJsonModel) {
            /** @var HalJsonRenderer $renderer */
            $renderer = $services->get(HalJsonRenderer::class);
            $content  = $renderer->render(nameOrModel: $result);

            $response->setContent(value: $content);
            $headers->addHeaderLine(
                headerFieldNameOrLine: 'Content-Type',
                fieldValue: $renderer->getLastContentType()
            );
            $e->setResult(result: $response);
            return;
        }

        $response->setContent(value: $result->serialize());
        $headers->addHeaderLine(headerFieldNameOrLine: 'Content-Type', fieldValue: 'application/json');
        $e->setResult(result: $response);
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
