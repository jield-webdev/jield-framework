<?php

namespace Jield\Mvc;

use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Router\RouteMatch;

use function str_replace;
use function str_starts_with;
use function ucwords;

class ModuleRouteListener extends AbstractListenerAggregate
{
    public const string MODULE_NAMESPACE    = '__NAMESPACE__';
    public const string ORIGINAL_CONTROLLER = '__CONTROLLER__';

    /**
     * Attach to an event manager
     *
     * @param int $priority
     */
    public function attach(EventManagerInterface $events, int $priority = 1): void
    {
        $this->listeners[] = $events->attach(eventName: MvcEvent::EVENT_ROUTE, listener: [$this, 'onRoute'], priority: $priority);
    }

    /**
     * Listen to the "route" event and determine if the module namespace should
     * be prepended to the controller name.
     *
     * If the route match contains a parameter key matching the MODULE_NAMESPACE
     * constant, that value will be prepended, with a namespace separator, to
     * the matched controller parameter.
     *
     * @return null
     */
    public function onRoute(MvcEvent $e): null
    {
        $matches = $e->getRouteMatch();
        if (! $matches instanceof RouteMatch) {
            // Can't do anything without a route match
            return;
        }

        $module = $matches->getParam(name: self::MODULE_NAMESPACE, default: false);
        if (! $module) {
            // No module namespace found; nothing to do
            return;
        }

        $controller = $matches->getParam(name: 'controller', default: false);
        if (! $controller) {
            // no controller matched, nothing to do
            return;
        }

        // Ensure the module namespace has not already been applied
        if (str_starts_with(haystack: $controller, needle: $module)) {
            return;
        }

        // Keep the originally matched controller name around
        $matches->setParam(name: self::ORIGINAL_CONTROLLER, value: $controller);

        // Prepend the controllername with the module, and replace it in the
        // matches
        $controller = $module . '\\' . str_replace(search: ' ', replace: '', subject: ucwords(string: str_replace(search: '-', replace: ' ', subject: $controller)));
        $matches->setParam(name: 'controller', value: $controller);
    }
}
