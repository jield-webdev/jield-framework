<?php

declare(strict_types=1);

namespace Jield\Mvc\Navigation\Service;

use Jield\Mvc\Application;
use Jield\Mvc\ApplicationInterface;
use Jield\Mvc\Navigation\MvcPage;
use Jield\Mvc\Router\RouteMatch;
use Jield\Stdlib\Service;
use Jield\Stdlib\Value;
use Laminas\Http\Request;
use Laminas\Navigation\Exception;
use Laminas\Navigation\Exception\InvalidArgumentException;
use Laminas\Navigation\Navigation;
use Laminas\Navigation\Page\Mvc;
use Laminas\Router\RouteStackInterface as Router;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\Stdlib\ArrayUtils;
use Psr\Container\ContainerInterface;
use Traversable;

use function assert;
use function get_debug_type;
use function is_array;
use function sprintf;

/**
 * Abstract navigation factory
 */
abstract class AbstractNavigationFactory implements FactoryInterface
{
    /** @var array<array-key, array<string, mixed>>|null */
    protected ?array $pages = null;

    /**
     * Create and return a new Navigation instance (v3).
     *
     * @param string $requestedName
     * @param null|array $options
     * @return Navigation
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): mixed
    {
        return new Navigation($this->getPages($container));
    }

    /**
     * @abstract
     * @return string
     */
    abstract protected function getName(): string;

    /**
     * @return array<array-key, array<string, mixed>>
     * @throws InvalidArgumentException
     */
    protected function getPages(ContainerInterface $container): ?array
    {
        if (null === $this->pages) {
            $configuration = $container->get('config');

            if (!isset($configuration['navigation'])) {
                throw new Exception\InvalidArgumentException('Could not find navigation configuration key');
            }
            if (!isset($configuration['navigation'][$this->getName()])) {
                throw new Exception\InvalidArgumentException(sprintf(
                    'Failed to find a navigation container by the name "%s"',
                    $this->getName(),
                ));
            }

            /** @var mixed $configuration */
            $configuration = $configuration['navigation'];
            $navigation = Value::accessible($configuration)[$this->getName()];
            $pages = $this->getPagesFromConfig($navigation);
            $this->pages = $this->preparePages($container, $pages);
        }

        return $this->pages;
    }

    /**
     * @param array<array-key, array<string, mixed>> $pages
     * @return array<array-key, array<string, mixed>>
     * @throws InvalidArgumentException
     */
    protected function preparePages(ContainerInterface $container, array $pages): array
    {
        $application = Service::get($container, ApplicationInterface::class, 'Application');
        assert($application instanceof Application);

        $routeMatch = $application->getMvcEvent()->getRouteMatch();
        $router = $application->getMvcEvent()->getRouter();
        $request = $application->getMvcEvent()->getRequest();

        // HTTP request is the only one that may be injected
        if (!$request instanceof Request) {
            $request = null;
        }

        return $this->injectComponents($pages, $routeMatch, $router, $request);
    }

    /**
     * @param mixed $config
     * @return array<array-key, array<string, mixed>>
     * @throws InvalidArgumentException
     */
    protected function getPagesFromConfig(mixed $config = null): array
    {
        if ($config instanceof Traversable) {
            $config = ArrayUtils::iteratorToArray($config);
        } elseif (!is_array($config)) {
            throw new Exception\InvalidArgumentException('Invalid input, expected array or Traversable object');
        }

        return Value::maps($config);
    }

    /**
     * @param array<array-key, array<string, mixed>> $pages
     * @param RouteMatch|null $routeMatch
     * @param Router|null $router
     * @param Request|null $request
     * @return array<array-key, array<string, mixed>>
     */
    protected function injectComponents(
        array $pages,
        ?RouteMatch $routeMatch = null,
        ?Router $router = null,
        ?Request $request = null,
    ): array {
        $this->validateRouteMatch($routeMatch);
        $this->validateRouter($router);

        foreach ($pages as &$page) {
            $hasUri = isset($page['uri']);
            $hasMvc = isset($page['action']) || isset($page['controller']) || isset($page['route']);
            if ($hasMvc || in_array($page['type'] ?? null, ['mvc', Mvc::class], true)) {
                $page['type'] = MvcPage::class;
                if (!isset($page['routeMatch']) && $routeMatch) {
                    $page['routeMatch'] = $routeMatch;
                }
                $page['router'] ??= $router;
            } elseif ($hasUri) {
                $page['request'] ??= $request;
            }

            if (isset($page['pages'])) {
                $page['pages'] = $this->injectComponents(
                    Value::maps($page['pages']),
                    $routeMatch,
                    $router,
                    $request,
                );
            }
        }

        return $pages;
    }

    /**
     * Validate that a route match argument provided to injectComponents is valid.
     *
     * @psalm-assert RouteMatch|null $routeMatch
     * @throws Exception\InvalidArgumentException
     */
    private function validateRouteMatch(mixed $routeMatch): void
    {
        if (null === $routeMatch) {
            return;
        }

        if (!$routeMatch instanceof RouteMatch) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expected by %s::injectComponents; received %s',
                RouteMatch::class,
                self::class,
                get_debug_type($routeMatch),
            ));
        }
    }

    /**
     * Validate that a router argument provided to injectComponents is valid.
     *
     * @psalm-assert Router|null $router
     * @throws Exception\InvalidArgumentException
     */
    private function validateRouter(mixed $router): void
    {
        if (null === $router) {
            return;
        }

        if (!$router instanceof Router) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expected by %s::injectComponents; received %s',
                RouteMatch::class,
                self::class,
                get_debug_type($router),
            ));
        }
    }
}
