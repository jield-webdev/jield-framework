<?php

declare(strict_types=1);

namespace Jield\ApiTools\Versioning;

use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;
use Laminas\EventManager\ListenerAggregateTrait;
use Jield\Mvc\MvcEvent;
use Laminas\Router\RouteMatch;
use Override;
use function preg_match;
use function preg_quote;
use function preg_replace;

class VersionListener implements ListenerAggregateInterface
{
    use ListenerAggregateTrait;

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function attach(EventManagerInterface $events, int $priority = 1): void
    {
        $this->listeners[] = $events->attach(eventName: MvcEvent::EVENT_ROUTE, listener: $this->onRoute(...), priority: -41);
    }

    /**
     * Determine if versioning is in the route matches, and update the controller accordingly
     *
     */
    public function onRoute(MvcEvent $e): ?RouteMatch
    {
        $routeMatches = $e->getRouteMatch();
        if (!($routeMatches instanceof RouteMatch)) {
            return null;
        }

        $version = $this->getVersionFromRouteMatch(routeMatches: $routeMatches);
        if (!$version) {
            // No version found in matches; done
            return null;
        }

        $controller = $routeMatches->getParam(name: 'controller', default: false);
        if (!$controller) {
            // no controller; we have bigger problems!
            return null;
        }

        $pattern = '#' . preg_quote(str: '\V') . '(\d+)' . preg_quote(str: '\\') . '#';
        if (!preg_match(pattern: $pattern, subject: (string) $controller, matches: $matches)) {
            // controller does not have a version subnamespace
            return null;
        }

        $replacement = preg_replace(pattern: $pattern, replacement: '\V' . $version . '\\', subject: (string) $controller);
        if ($controller === $replacement) {
            return null;
        }

        $routeMatches->setParam(name: 'controller', value: $replacement);
        return $routeMatches;
    }

    /**
     * Retrieve the version from the route match.
     *
     * The route prototype sets "version", while the Content-Type listener sets
     * "jield_ver_version"; check both to obtain the version, giving priority to the
     * route prototype result.
     *
     * @param RouteMatch $routeMatches
     * @return int|false
     */
    protected function getVersionFromRouteMatch(RouteMatch $routeMatches): false|int
    {
        $version = $routeMatches->getParam(name: 'jield_ver_version', default: false);
        if ($version) {
            return $version;
        }

        return $routeMatches->getParam(name: 'version', default: false);
    }
}
