<?php

declare(strict_types=1);

namespace Jield\ErrorHandler\Listener;

use ErrorException;
use Jield\ErrorHandler\Handler\Logging;
use Jield\ErrorHandler\HeroTrait;
use Jield\Mvc\MvcEvent;
use Jield\Mvc\SendResponseListener;
use Jield\Mvc\View\Renderer\PhpRenderer;
use Jield\Stdlib\Value;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Http\PhpEnvironment\Response;
use Laminas\Stdlib\RequestInterface;
use Override;
use Throwable;
use Webmozart\Assert\Assert;

use function Jield\ErrorHandler\detectMessageContentType;
use function Jield\ErrorHandler\isExcludedException;

final class Mvc extends AbstractListenerAggregate
{
    use HeroTrait;

    protected function getErrorHandlerConfiguration(): array
    {
        return $this->errorHeroModuleConfig;
    }

    protected function capturesErrorOutput(): bool
    {
        return false;
    }

    /**
     * @throws \Laminas\View\Exception\RenderingFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \JsonException
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface
     */
    protected function handleShutdownException(ErrorException $exception): void
    {
        $event = Assert::notNull($this->mvcEvent);
        $event->setParam(name: 'exception', value: $exception);
        $this->exceptionError($event);
    }

    private const string DISPLAY_SETTINGS = 'display-settings';

    private const string MESSAGE = 'message';

    public function __construct(
        private readonly array $errorHeroModuleConfig,
        private readonly Logging $logging,
        private readonly PhpRenderer $phpRenderer,
    ) {}

    /**
     * @param int $priority
     */
    #[Override]
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        if (!$this->errorHeroModuleConfig['enable']) {
            return;
        }

        // exceptions
        $this->listeners[] = $events->attach(
            eventName: MvcEvent::EVENT_RENDER_ERROR,
            listener: $this->exceptionError(...),
        );
        $this->listeners[] = $events->attach(
            eventName: MvcEvent::EVENT_DISPATCH_ERROR,
            listener: $this->exceptionError(...),
            priority: 100,
        );

        // php errors
        $this->listeners[] = $events->attach(eventName: MvcEvent::EVENT_BOOTSTRAP, listener: $this->phpError(...));
    }

    /**
     * @throws \Laminas\View\Exception\RenderingFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \JsonException
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface
     */
    public function exceptionError(MvcEvent $mvcEvent): void
    {
        /** @mago-var mixed $exception */
        $exception = $mvcEvent->getParam(name: 'exception');
        if (!$exception instanceof Throwable) {
            return;
        }

        if (
            isset($this->errorHeroModuleConfig[self::DISPLAY_SETTINGS]['exclude-exceptions'])
            && isExcludedException(
                excludeExceptionsConfig: Assert::isArray($this->errorHeroModuleConfig[self::DISPLAY_SETTINGS]['exclude-exceptions']),
                throwable: $exception,
            )
        ) {
            // rely on original mvc process
            return;
        }

        $this->logging->handleErrorException(throwable: $exception, request: $request = $mvcEvent->getRequest());

        if (Value::accessible($this->errorHeroModuleConfig[self::DISPLAY_SETTINGS])['display_errors']) {
            // rely on original mvc process
            return;
        }

        // show default view if display_errors setting = 0.
        $this->showDefaultView(mvcEvent: $mvcEvent, request: $request);
    }

    /**
     * @throws \Laminas\View\Exception\RenderingFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    private function showDefaultView(MvcEvent $mvcEvent, RequestInterface $request): void
    {
        Assert::isInstanceOf(value: $request, class: Request::class);

        $response = $mvcEvent->getResponse();
        Assert::isInstanceOf(value: $response, class: Response::class);
        $response->setStatusCode(code: 500);

        $application = $mvcEvent->getApplication();
        $eventManager = Assert::notNull($application)->getEventManager();
        $serviceLocator = Assert::notNull($application)->getServiceManager();

        /** @var SendResponseListener $sendResponseListener */
        $sendResponseListener = $serviceLocator->get('SendResponseListener');
        $sendResponseListener->detach(events: $eventManager);

        $isXmlHttpRequest = $request->isXmlHttpRequest();
        if ($isXmlHttpRequest && isset($this->errorHeroModuleConfig[self::DISPLAY_SETTINGS]['ajax'][self::MESSAGE])) {
            /** @mago-var mixed $message */
            $message = Value::path($this->errorHeroModuleConfig, [self::DISPLAY_SETTINGS, 'ajax', self::MESSAGE]);
            $contentType = detectMessageContentType(message: Assert::string($message));

            $response->getHeaders()->addHeaderLine('Content-type', $contentType);
            $response->setContent($message);
            $response->send();

            return;
        }

        $model = $mvcEvent->getViewModel();
        $model->setTemplate(template: Assert::stringNotEmpty(Value::path(
            $this->errorHeroModuleConfig,
            [self::DISPLAY_SETTINGS, 'template', 'layout'],
        )));
        $model->setVariable(
            name: $model->captureTo(),
            value: $this->phpRenderer->render(Assert::stringNotEmpty(Value::path(
                $this->errorHeroModuleConfig,
                [self::DISPLAY_SETTINGS, 'template', 'view'],
            ))),
        );

        $response->setContent($this->phpRenderer->render($model));
        $response->send();
    }
}
