<?php

declare(strict_types=1);

namespace JieldTest\Integration;

use ArrayIterator;
use Jield\Authorize\Config;
use Jield\Cors\Options\CorsOptions;
use Jield\Mvc\Controller\AbstractActionController;
use Jield\Mvc\MvcEvent;
use Jield\Mvc\Navigation\View\Navigation\Menu;
use Jield\Mvc\Router\RouteMatch;
use Jield\Mvc\View\Model\ViewModel;
use Jield\Mvc\View\Renderer\PhpRenderer;
use Jield\Stdlib\Service;
use Jield\Stdlib\Value;
use Laminas\Http\Response;
use Laminas\ServiceManager\ServiceManager;
use Laminas\View\Helper\HelperInterface;
use Laminas\View\Helper\HtmlAttributes;
use Laminas\View\HelperPluginManager;
use Laminas\View\Renderer\PhpRenderer as NativeRenderer;
use Laminas\View\Resolver\TemplateMapResolver;
use PHPUnit\Framework\TestCase;
use stdClass;
use TypeError;

final class BoundaryContractsTest extends TestCase
{
    public function testClassNamedServicesCanContainConfigurationOrBeDisabled(): void
    {
        $configuration = ['default_role' => 'guest'];
        $services = new ServiceManager(['services' => [Config::class => $configuration, stdClass::class => false]]);

        self::assertSame($configuration, Service::config($services, Config::class));
        self::assertFalse(Service::getOrFalse($services, stdClass::class));

        $service = new stdClass();
        $services = new ServiceManager(['services' => [stdClass::class => $service]]);
        self::assertSame($service, Service::getOrFalse($services, stdClass::class));
    }

    public function testConfigurationServicesRejectUnexpectedObjects(): void
    {
        $services = new ServiceManager(['services' => [Config::class => new Config()]]);

        $this->expectException(TypeError::class);
        Service::config($services, Config::class);
    }

    public function testTraversableOptionsReachTheirTypedOptionsObject(): void
    {
        $origins = ['https://app.example.test'];
        $options = new CorsOptions(Value::options(new ArrayIterator(['allowed_origins' => $origins])));

        self::assertSame($origins, $options->getAllowedOrigins());
        self::assertSame($options, Value::options($options));
    }

    public function testNotFoundActionsAcceptApplicationProvidedCallables(): void
    {
        $model = new ViewModel(['message' => 'custom']);
        $controller = new class ($model) extends AbstractActionController {
            public function __construct(private readonly ViewModel $model)
            {
            }

            public function plugin($name, ?array $options = null): mixed
            {
                return fn (Response $response): ViewModel => $this->model;
            }
        };
        $event = new MvcEvent();
        $event->setResponse(new Response());
        $event->setRouteMatch(new RouteMatch([]));
        $controller->setEvent($event);

        self::assertSame($model, $controller->notFoundAction());
        self::assertSame('not-found', $event->getRouteMatch()->getParam('action'));
    }

    public function testNavigationAcceptsApplicationProvidedAttributeHelpers(): void
    {
        $attributes = new class implements HelperInterface {
            public function __invoke(array $attributes): string
            {
                return 'data-count="' . count($attributes) . '"';
            }
        };
        $helpers = new HelperPluginManager(
            new ServiceManager(),
            ['services' => [HtmlAttributes::class => $attributes]],
        );
        $renderer = new PhpRenderer(new NativeRenderer($helpers, new TemplateMapResolver()), $helpers);
        $navigation = new class extends Menu {
            public function renderAttributes(array $attributes): string
            {
                return $this->htmlAttribs($attributes);
            }
        };
        $navigation->setView($renderer);

        self::assertSame(' data-count="1"', $navigation->renderAttributes(['id' => 'entry', 'title' => null]));
    }
}
