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
use function is_array;
use function sprintf;

/**
 * Abstract navigation factory
 */
// Preserve the existing public API and the state and operations that implement it.
// @mago-expect lint:cyclomatic-complexity(1)
abstract class AbstractNavigationFactory implements FactoryInterface
{
    /** @var array<array-key, array<string, mixed>>|null */
    protected ?array $pages = null;

    /**
     * Create and return a new Navigation instance (v3).
     *
     * @return Navigation
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): mixed
    {
        return new Navigation($this->getPages($container));
    }

    /**
     * @abstract
     */
    abstract protected function getName(): string;

    /**
     * @return array<array-key, array<string, mixed>>
     * @throws InvalidArgumentException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function getPages(ContainerInterface $container): ?array
    {
        if (null === $this->pages) {
            /** @mago-var mixed $configuration */
            $configuration = $container->get('config');

            if (($configuration['navigation'] ?? null) === null) {
                throw new Exception\InvalidArgumentException('Could not find navigation configuration key');
            }
            if (($configuration['navigation'][$this->getName()] ?? null) === null) {
                throw new Exception\InvalidArgumentException(sprintf(
                    'Failed to find a navigation container by the name "%s"',
                    $this->getName(),
                ));
            }

            /** @mago-var mixed $configuration */
            $configuration = $configuration['navigation'];
            /** @mago-var mixed $navigation */
            $navigation = \Jield\Stdlib\Value::accessible($configuration)[$this->getName()];
            $pages = $this->getPagesFromConfig($navigation);
            $this->pages = $this->preparePages($container, $pages);
        }

        return $this->pages;
    }

    /**
     * @param array<array-key, array<string, mixed>> $pages
     * @return array<array-key, array<string, mixed>>
     * @throws InvalidArgumentException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function preparePages(ContainerInterface $container, array $pages): array
    {
        $application = Service::get($container, ApplicationInterface::class, 'Application');
        assert($application instanceof Application, 'Expected $application instanceof Application.');

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
     * @return array<array-key, array<string, mixed>>
     * @throws InvalidArgumentException
     */
    protected function getPagesFromConfig(mixed $config = null): array
    {
        if ($config instanceof Traversable) {
            $config = ArrayUtils::iteratorToArray($config);
            return Value::maps($config);
        }
        if (!is_array($config)) {
            throw new Exception\InvalidArgumentException('Invalid input, expected array or Traversable object');
        }
        return Value::maps($config);
    }

    /**
     * @param array<array-key, array<string, mixed>> $pages
     * @return array<array-key, array<string, mixed>>
     */
    protected function injectComponents(
        array $pages,
        ?RouteMatch $routeMatch = null,
        ?Router $router = null,
        ?Request $request = null,
    ): array {
        foreach ($pages as &$page) {
            $hasUri = ($page['uri'] ?? null) !== null;
            $hasMvc =
                ($page['action'] ?? null) !== null
                || ($page['controller'] ?? null) !== null
                || ($page['route'] ?? null) !== null;
            // These alternatives are mutually exclusive; the following code consumes the selected state.
            // @mago-expect lint:no-else-clause(1)
            if ($hasMvc || in_array($page['type'] ?? null, ['mvc', Mvc::class], true)) {
                $page['type'] = MvcPage::class;
                if (($page['routeMatch'] ?? null) === null && $routeMatch) {
                    $page['routeMatch'] = $routeMatch;
                }
                $page['router'] ??= $router;
            } elseif ($hasUri) {
                $page['request'] ??= $request;
            }

            if (($page['pages'] ?? null) !== null) {
                $page['pages'] = $this->injectComponents(
                    \Jield\Stdlib\Value::maps($page['pages']),
                    $routeMatch,
                    $router,
                    $request,
                );
            }
        }

        return $pages;
    }
}
