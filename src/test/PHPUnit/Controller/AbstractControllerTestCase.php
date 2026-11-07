<?php

declare(strict_types=1);

namespace Jield\Test\PHPUnit\Controller;

use Exception;
use Jield\ModuleManager\ModuleManager;
use Jield\Mvc\Application;
use Jield\Mvc\ApplicationInterface;
use Jield\Mvc\Controller\ControllerManager;
use Jield\Mvc\MvcEvent;
use Jield\Mvc\Router\RouteMatch;
use Jield\Mvc\SendResponseListener;
use Jield\Stdlib\Service;
use Jield\Stdlib\Value;
use Jield\Test\PHPUnit\Constraint\IsCurrentModuleNameConstraint;
use Jield\Test\PHPUnit\FailureMessage;
use Laminas\EventManager\ResponseCollection;
use Laminas\Http\AbstractMessage;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Http\Request as HttpRequest;
use Laminas\Http\Response;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\Exception\LogicException;
use Laminas\Stdlib\Parameters;
use Laminas\Stdlib\RequestInterface;
use Laminas\Stdlib\ResponseInterface;
use Laminas\Uri\Http as HttpUri;
use Laminas\View\Model\ModelInterface;
use Override;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Throwable;
use Webmozart\Assert\Assert as WebmozartAssert;

use function array_diff;
use function array_intersect;
use function array_key_exists;
use function array_keys;
use function array_merge;
use function assert;
use function count;
use function http_build_query;
use function implode;
use function parse_str;
use function sprintf;
use function strrpos;
use function strtolower;
use function substr;
use function trigger_error;

use const E_USER_NOTICE;

// Preserve the existing public API and the state and operations that implement it.
// @mago-expect lint:cyclomatic-complexity(1)
// @mago-expect lint:kan-defect(1)
// @mago-expect lint:too-many-methods(1)
abstract class AbstractControllerTestCase extends TestCase
{
    /** @var ApplicationInterface|null */
    protected $application;

    /** @var array|null */
    protected $applicationConfig;

    /**
     * Trace error when exception is throwed in application
     *
     * @var bool
     */
    protected $traceError = true;

    /**
     * Reset the application for isolation
     */
    #[Override]
    protected function setUp(): void
    {
        $this->reset();
    }

    /**
     * Restore params
     */
    #[Override]
    protected function tearDown(): void
    {
        // Prevent memory leak
        $this->reset();
    }

    /**
     * Create a failure message.
     *
     * If $traceError is true, appends exception details, if any.
     *
     * @deprecated (use LaminasContraint instead)
     *
     * @param string $message
     * @return string
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function createFailureMessage($message)
    {
        return FailureMessage::format($this, $message);
    }

    /**
     * Get the trace error flag
     *
     * @return bool
     */
    public function getTraceError()
    {
        return $this->traceError;
    }

    /**
     * Set the trace error flag
     *
     * @param  bool                       $traceError
     * @return AbstractControllerTestCase
     */
    public function setTraceError($traceError)
    {
        $this->traceError = $traceError;

        return $this;
    }

    /**
     * Get the application config
     *
     * @return array the application config
     */
    public function getApplicationConfig()
    {
        return $this->applicationConfig ?? [];
    }

    /**
     * Set the application config
     *
     * @param  array                      $applicationConfig
     * @return AbstractControllerTestCase
     * @throws LogicException
     */
    public function setApplicationConfig($applicationConfig)
    {
        if (null !== $this->applicationConfig) {
            throw new LogicException('Application config can not be set, the application is already built');
        }

        // do not cache module config on testing environment
        if (($applicationConfig['module_listener_options']['config_cache_enabled'] ?? null) !== null) {
            $applicationConfig['module_listener_options']['config_cache_enabled'] = false;
        }
        $this->applicationConfig = $applicationConfig;

        return $this;
    }

    /**
     * Get the application object
     *
     * @return ApplicationInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function getApplication()
    {
        if ($this->application) {
            return $this->application;
        }
        $appConfig = $this->applicationConfig;
        $this->application = Application::init(WebmozartAssert::isArray($appConfig));

        $events = $this->application->getEventManager();
        Service::get(
            $this->application->getServiceManager(),
            SendResponseListener::class,
            'SendResponseListener',
        )->detach($events);

        return $this->application;
    }

    /**
     * Get the service manager of the application object
     *
     * @return ServiceManager
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function getApplicationServiceLocator()
    {
        return WebmozartAssert::isInstanceOf($this->getApplication()->getServiceManager(), ServiceManager::class);
    }

    /**
     * Get the application request object
     *
     * @return RequestInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function getRequest()
    {
        return $this->getApplication()->getRequest();
    }

    /**
     * Get the application response object
     *
     * @return Response
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function getResponse()
    {
        $response = $this->getApplication()->getMvcEvent()->getResponse();

        assert($response instanceof Response, 'Expected $response instanceof Response.');

        return $response;
    }

    /**
     * Set the request URL
     *
     * @param  string                     $url
     * @param  string|null                $method
     * @param  array|null                 $params
     * @return AbstractControllerTestCase
     * @throws \ReflectionException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function url($url, $method = HttpRequest::METHOD_GET, $params = [])
    {
        $request = $this->getRequest();
        /** @mago-var mixed $query */
        $query = Value::call(
            WebmozartAssert::isInstanceOf($request, \Laminas\Http\Request::class)->getQuery(),
            'toArray',
        );
        /** @mago-var mixed $post */
        $post = Value::call(
            WebmozartAssert::isInstanceOf($request, \Laminas\Http\Request::class)->getPost(),
            'toArray',
        );
        $uri = new HttpUri($url);
        $queryString = $uri->getQuery();

        if (null !== $queryString) {
            parse_str($queryString, $query);
        }

        if (null !== $params && [] !== $params) {
            switch ($method) {
                case HttpRequest::METHOD_POST:
                    $post = $params;
                    break;
                case HttpRequest::METHOD_GET:
                case HttpRequest::METHOD_DELETE:
                    $query = array_merge(\Webmozart\Assert\Assert::isArray($query), $params);
                    break;
                case HttpRequest::METHOD_PUT:
                case HttpRequest::METHOD_PATCH:
                    $content = http_build_query($params);
                    $request->setContent($content);
                    break;
                default:
                    trigger_error(
                        'Additional params is only supported by GET, POST, PUT and PATCH HTTP method',
                        E_USER_NOTICE,
                    );
            }
        }

        WebmozartAssert::isInstanceOf($request, \Laminas\Http\Request::class)->setMethod(WebmozartAssert::string(
            $method,
        ));
        WebmozartAssert::isInstanceOf(
            $request,
            \Laminas\Http\Request::class,
        )->setQuery(new Parameters(Value::nullableArray($query)));
        WebmozartAssert::isInstanceOf(
            $request,
            \Laminas\Http\Request::class,
        )->setPost(new Parameters(Value::nullableArray($post)));
        WebmozartAssert::isInstanceOf($request, \Laminas\Http\Request::class)->setUri($uri);
        WebmozartAssert::isInstanceOf($request, Request::class)->setRequestUri(
            WebmozartAssert::string($uri->getPath()),
        );

        return $this;
    }

    /**
     * Dispatch the MVC with a URL
     * Accept a HTTP (simulate a customer action)
     *
     * The URL provided set the request URI in the request object.
     *
     * @param string      $url
     * @param string|null $method
     * @param array|null  $params
     * @param bool        $isXmlHttpRequest
     * @return void
     * @throws Exception
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function dispatch($url, $method = null, $params = [], $isXmlHttpRequest = false)
    {
        // These alternatives are mutually exclusive; the following code consumes the selected state.
        // @mago-expect lint:no-else-clause(1)
        if (null === $method && $this->getRequest() instanceof HttpRequest) {
            $method = \Webmozart\Assert\Assert::isInstanceOf(
                $this->getRequest(),
                \Laminas\Http\Request::class,
            )->getMethod();
        } elseif (null === $method) {
            $method = HttpRequest::METHOD_GET;
        }

        if ($isXmlHttpRequest) {
            $headers = WebmozartAssert::isInstanceOf($this->getRequest(), AbstractMessage::class)->getHeaders();
            Value::call($headers, 'addHeaderLine', ['X_REQUESTED_WITH', 'XMLHttpRequest']);
        }

        $this->url($url, \Webmozart\Assert\Assert::nullOrString($method), $params);
        $this->getApplication()->run();
    }

    /**
     * Reset the request
     *
     * @param bool $keepPersistence
     * @return AbstractControllerTestCase
     */
    public function reset($keepPersistence = false)
    {
        // force to re-create all components
        $this->application = null;

        // reset server data
        if (!$keepPersistence) {
            // Do not create a global session variable if it doesn't already
            // exist. Otherwise calling this function could mark tests risky,
            // as it changes global state.
            // Inspect session existence without creating a session global during test reset.
            // @mago-expect lint:no-global(1)
            if (array_key_exists('_SESSION', $GLOBALS)) {
                $_SESSION = [];
            }
            $_COOKIE = [];
        }

        $_GET = [];
        $_POST = [];

        return $this;
    }

    /**
     * Trigger an application event
     *
     * @param  string                                $eventName
     * @return ResponseCollection
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function triggerApplicationEvent($eventName)
    {
        $events = $this->getApplication()->getEventManager();
        $event = $this->getApplication()->getMvcEvent();

        if ($eventName !== MvcEvent::EVENT_ROUTE && $eventName !== MvcEvent::EVENT_DISPATCH) {
            return $events->trigger($eventName, $event);
        }

        $shortCircuit = static function ($r) use ($event): bool {
            if ($r instanceof ResponseInterface) {
                return true;
            }

            if ($event->getError()) {
                return true;
            }

            return false;
        };

        $event->setName($eventName);
        return $events->triggerEventUntil($shortCircuit, $event);
    }

    /**
     * Assert modules were loaded with the module manager
     *
     * @return void
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertModulesLoaded(array $modules)
    {
        /** @mago-var mixed $moduleManager */
        $moduleManager = $this->getApplicationServiceLocator()->get('ModuleManager');
        assert($moduleManager instanceof ModuleManager, 'Expected $moduleManager instanceof ModuleManager.');
        $modulesLoaded = array_keys($moduleManager->getLoadedModules());
        $list = array_diff(Value::strings($modules), Value::strings($modulesLoaded));
        if ($list) {
            throw new ExpectationFailedException(FailureMessage::format($this, sprintf('Several modules are not loaded "%s"', implode(
                ', ',
                $list,
            ))));
        }
        $this->assertEquals(count($list), 0);
    }

    /**
     * Assert modules were not loaded with the module manager
     *
     * @return void
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \ReflectionException
     */
    public function assertNotModulesLoaded(array $modules)
    {
        /** @mago-var mixed $moduleManager */
        $moduleManager = $this->getApplicationServiceLocator()->get('ModuleManager');
        /** @mago-var mixed $modulesLoaded */
        $modulesLoaded = Value::call($moduleManager, 'getModules');
        $list = array_intersect($modules, \Webmozart\Assert\Assert::isArray($modulesLoaded));
        if ($list) {
            throw new ExpectationFailedException(FailureMessage::format($this, sprintf('Several modules WAS not loaded "%s"', implode(
                ', ',
                Value::scalars($list),
            ))));
        }
        $this->assertEquals(count($list), 0);
    }

    /**
     * Retrieve the response status code
     *
     * @return int
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function getResponseStatusCode()
    {
        return $this->getResponse()->getStatusCode();
    }

    /**
     * Assert response status code
     *
     * @param int $code
     * @return void
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertResponseStatusCode($code)
    {
        $match = $this->getResponseStatusCode();
        if ($code !== $match) {
            throw new ExpectationFailedException(FailureMessage::format($this, sprintf(
                'Failed asserting response code "%s", actual status code is "%s"',
                $code,
                $match,
            )));
        }
        $this->assertEquals($code, $match);
    }

    /**
     * Assert not response status code
     *
     * @param int $code
     * @return void
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertNotResponseStatusCode($code)
    {
        $match = $this->getResponseStatusCode();
        if ($code === $match) {
            throw new ExpectationFailedException(FailureMessage::format($this, sprintf(
                'Failed asserting response code was NOT "%s"',
                $code,
            )));
        }
        $this->assertNotEquals($code, $match);
    }

    /**
     * Assert the application exception and message
     *
     * @param string $type application exception type
     * @param string|null $message application exception message
     * @psalm-return never
     * @throws Throwable
     */
    public function assertApplicationException($type, $message = null)
    {
        /** @mago-var mixed $exception */
        $exception = $this->getApplication()->getMvcEvent()->getParam('exception');
        if (!$exception) {
            throw new ExpectationFailedException(FailureMessage::format(
                $this,
                'Failed asserting application exception, param "exception" does not exist',
            ));
        }
        if (true === $this->traceError) {
            // set exception as null because we know and have assert the exception
            $this->getApplication()->getMvcEvent()->setParam('exception', null);
        }

        WebmozartAssert::isAOf($type, \Throwable::class);
        $this->expectException($type);
        if (null !== $message) {
            $this->expectExceptionMessageIsOrContains($message);
        }

        throw WebmozartAssert::isInstanceOf($exception, \Throwable::class);
    }

    /**
     * Get the full current controller class name
     *
     * @return string
     * @throws \PHPUnit\Framework\Exception
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function getControllerFullClassName()
    {
        return $this->getControllerFullClass()::class;
    }

    /**
     * Get the current controller class
     *
     * @return object
     * @throws \PHPUnit\Framework\Exception
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function getControllerFullClass()
    {
        $routeMatch = $this->getApplication()->getMvcEvent()->getRouteMatch();
        if (!$routeMatch) {
            Assert::fail('No route matched');
        }

        /** @mago-var mixed $controllerIdentifier */
        $controllerIdentifier = $routeMatch->getParam('controller');
        Assert::assertIsString($controllerIdentifier, 'No string controller identifier discovered in route match');

        /** @mago-var mixed $controllerManager */
        $controllerManager = $this->getApplicationServiceLocator()->get('ControllerManager');
        if (!$controllerManager instanceof ControllerManager) {
            Assert::fail('Invalid ControllerManager instance in ServiceManager');
        }

        /** @mago-var mixed $controller */
        $controller = $controllerManager->get($controllerIdentifier);
        Assert::assertIsObject($controller, sprintf(
            'Did not receive an object back for the controller %s',
            $controllerIdentifier,
        ));

        return $controller;
    }

    /**
     * Assert that the application route match used the given module
     *
     * @param string $module
     * @return void
     * @throws ExpectationFailedException
     */
    public function assertModuleName($module)
    {
        self::assertThat($module, new IsCurrentModuleNameConstraint($this));
    }

    /**
     * Assert that the application route match used NOT the given module
     *
     * @param string $module
     * @return void
     * @throws ExpectationFailedException
     */
    public function assertNotModuleName($module)
    {
        self::assertThat($module, self::logicalNot(new IsCurrentModuleNameConstraint($this)));
    }

    /**
     * Assert that the application route match used the given controller class
     *
     * @param string $controller
     * @return void
     * @throws \PHPUnit\Framework\Exception
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertControllerClass($controller)
    {
        $controllerClass = $this->getControllerFullClassName();
        $position = strrpos($controllerClass, '\\');
        $match = substr($controllerClass, ($position ? $position : -1) + 1);
        $match = strtolower($match);
        $controller = strtolower($controller);
        if ($controller !== $match) {
            throw new ExpectationFailedException(FailureMessage::format($this, sprintf(
                'Failed asserting controller class "%s", actual controller class is "%s"',
                $controller,
                $match,
            )));
        }
        $this->assertEquals($controller, $match);
    }

    /**
     * Assert that the application route match used NOT the given controller class
     *
     * @param string $controller
     * @return void
     * @throws \PHPUnit\Framework\Exception
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertNotControllerClass($controller)
    {
        $controllerClass = $this->getControllerFullClassName();
        $position = strrpos($controllerClass, '\\');
        $match = substr($controllerClass, ($position ? $position : -1) + 1);
        $match = strtolower($match);
        $controller = strtolower($controller);
        if ($controller === $match) {
            throw new ExpectationFailedException(FailureMessage::format($this, sprintf(
                'Failed asserting controller class was NOT "%s"',
                $controller,
            )));
        }
        $this->assertNotEquals($controller, $match);
    }

    /**
     * Assert that the application route match used the given controller name
     *
     * @param string $controller
     * @return void
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertControllerName($controller)
    {
        $routeMatch = $this->getApplication()->getMvcEvent()->getRouteMatch();
        if (!$routeMatch) {
            throw new ExpectationFailedException(FailureMessage::format($this, 'No route matched'));
        }
        /** @mago-var mixed $match */
        $match = $routeMatch->getParam('controller');
        $match = strtolower(\Webmozart\Assert\Assert::string($match));
        $controller = strtolower($controller);
        if ($controller !== $match) {
            throw new ExpectationFailedException(FailureMessage::format($this, sprintf(
                'Failed asserting controller name "%s", actual controller name is "%s"',
                $controller,
                $match,
            )));
        }
        $this->assertEquals($controller, $match);
    }

    /**
     * Assert that the application route match used NOT the given controller name
     *
     * @param string $controller
     * @return void
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertNotControllerName($controller)
    {
        $routeMatch = $this->getApplication()->getMvcEvent()->getRouteMatch();
        if (!$routeMatch) {
            throw new ExpectationFailedException(FailureMessage::format($this, 'No route matched'));
        }
        /** @mago-var mixed $match */
        $match = $routeMatch->getParam('controller');
        $match = strtolower(\Webmozart\Assert\Assert::string($match));
        $controller = strtolower($controller);
        if ($controller === $match) {
            throw new ExpectationFailedException(FailureMessage::format($this, sprintf(
                'Failed asserting controller name was NOT "%s"',
                $controller,
            )));
        }
        $this->assertNotEquals($controller, $match);
    }

    /**
     * Assert that the application route match used the given action
     *
     * @param string $action
     * @return void
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertActionName($action)
    {
        $routeMatch = $this->getApplication()->getMvcEvent()->getRouteMatch();
        if (!$routeMatch) {
            throw new ExpectationFailedException(FailureMessage::format($this, 'No route matched'));
        }
        /** @mago-var mixed $match */
        $match = $routeMatch->getParam('action');
        $match = strtolower(\Webmozart\Assert\Assert::string($match));
        $action = strtolower($action);
        if ($action !== $match) {
            throw new ExpectationFailedException(FailureMessage::format($this, sprintf(
                'Failed asserting action name "%s", actual action name is "%s"',
                $action,
                $match,
            )));
        }
        $this->assertEquals($action, $match);
    }

    /**
     * Assert that the application route match used NOT the given action
     *
     * @param string $action
     * @return void
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertNotActionName($action)
    {
        $routeMatch = $this->getApplication()->getMvcEvent()->getRouteMatch();
        if (!$routeMatch) {
            throw new ExpectationFailedException(FailureMessage::format($this, 'No route matched'));
        }
        /** @mago-var mixed $match */
        $match = $routeMatch->getParam('action');
        $match = strtolower(\Webmozart\Assert\Assert::string($match));
        $action = strtolower($action);
        if ($action === $match) {
            throw new ExpectationFailedException(FailureMessage::format($this, sprintf(
                'Failed asserting action name was NOT "%s"',
                $action,
            )));
        }
        $this->assertNotEquals($action, $match);
    }

    /**
     * Assert that the application route match used the given route name
     *
     * @param string $route
     * @return void
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertMatchedRouteName($route)
    {
        $routeMatch = $this->getApplication()->getMvcEvent()->getRouteMatch();
        if (!$routeMatch) {
            throw new ExpectationFailedException(FailureMessage::format($this, 'No route matched'));
        }
        $match = $routeMatch->getMatchedRouteName();
        $match = strtolower($match);
        $route = strtolower($route);
        if ($route !== $match) {
            throw new ExpectationFailedException(FailureMessage::format($this, sprintf(
                'Failed asserting matched route name was "%s", actual matched route name is "%s"',
                $route,
                $match,
            )));
        }
        $this->assertEquals($route, $match);
    }

    /**
     * Assert that the application route match used NOT the given route name
     *
     * @param string $route
     * @return void
     * @throws \PHPUnit\Framework\AssertionFailedError
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertNotMatchedRouteName($route)
    {
        $application = $this->getApplication();
        if (!$application instanceof Application) {
            Assert::fail(sprintf(
                'Unexpected Application instance composed in test case; must be of type %s',
                Application::class,
            ));
        }

        $routeMatch = $application->getMvcEvent()->getRouteMatch();
        if (!$routeMatch) {
            Assert::fail('No route matched');
        }

        $match = $routeMatch->getMatchedRouteName();
        $match = strtolower($match);
        $route = strtolower($route);
        if ($route === $match) {
            Assert::fail(sprintf('Failed asserting route matched was NOT "%s"', $route));
        }

        $this->assertNotEquals($route, $match);
    }

    /**
     * Assert that the application did not match any route
     *
     * @return void
     * @throws \PHPUnit\Framework\AssertionFailedError
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertNoMatchedRoute()
    {
        $application = $this->getApplication();
        if (!$application instanceof Application) {
            Assert::fail(sprintf(
                'Unexpected Application instance composed in test case; must be of type %s',
                Application::class,
            ));
        }

        $routeMatch = $application->getMvcEvent()->getRouteMatch();

        if (!$routeMatch instanceof RouteMatch) {
            $this->addToAssertionCount(1);
            return;
        }

        $match = $routeMatch->getMatchedRouteName();
        $match = strtolower($match);
        Assert::fail(sprintf('Failed asserting that no route matched, actual matched route name is "%s"', $match));
    }

    /**
     * Assert template name
     * Assert that a template was used somewhere in the view model tree
     *
     * @param string $templateName
     * @return void
     * @throws \PHPUnit\Framework\AssertionFailedError
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertTemplateName($templateName)
    {
        $application = $this->getApplication();
        if (!$application instanceof Application) {
            $this->fail(sprintf(
                'Unexpected Application instance composed in test case; must be of type %s',
                Application::class,
            ));
        }

        $viewModel = $application->getMvcEvent()->getViewModel();
        $this->assertTrue(
            $this->searchTemplates($viewModel, $templateName),
            sprintf('Failed asserting that view model tree contains template "%s"', $templateName),
        );
    }

    /**
     * Assert not template name
     * Assert that a template was not used somewhere in the view model tree
     *
     * @param string $templateName
     * @return void
     * @throws ExpectationFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function assertNotTemplateName($templateName)
    {
        $viewModel = $this->getApplication()->getMvcEvent()->getViewModel();
        $this->assertFalse(
            $this->searchTemplates($viewModel, $templateName),
            sprintf('Failed asserting that view model tree does not contain template "%s"', $templateName),
        );
    }

    /**
     * Recursively search a view model and it's children for the given templateName
     *
     * @param ModelInterface $viewModel
     * @param  string    $templateName
     * @return boolean
     */
    protected function searchTemplates($viewModel, $templateName)
    {
        if ($viewModel->getTemplate() === $templateName) {
            return true;
        }

        foreach ($viewModel->getChildren() as $child) {
            if ($this->searchTemplates($child, $templateName)) {
                return true;
            }
        }

        return false;
    }
}
