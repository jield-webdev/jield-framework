<?php

namespace Jield\Mvc\View\Http;

use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface as Events;
use Jield\Mvc\MvcEvent;
use Jield\Stdlib\StringUtils;
use Laminas\View\Model\ModelInterface as ViewModel;

use function array_diff;
use function array_pop;
use function explode;
use function implode;
use function is_object;
use function is_string;
use function krsort;
use function preg_replace;
use function rtrim;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function strrpos;
use function strtolower;
use function substr;
use function trim;

class InjectTemplateListener extends AbstractListenerAggregate
{
    /**
     * Array of controller namespace -> template mappings
     *
     * @var array
     */
    protected array $controllerMap = [];

    /**
     * Flag to force the use of the route match controller param
     *
     * @var boolean
     */
    protected bool $preferRouteMatchController = false;

    /**
     * {@inheritDoc}
     */
    public function attach(Events $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(eventName: MvcEvent::EVENT_DISPATCH, listener: [$this, 'injectTemplate'], priority: -90);
    }

    /**
     * Inject a template into the view model, if none present
     *
     * Template is derived from the controller found in the route match, and,
     * optionally, the action, if present.
     *
     * @return void
     */
    public function injectTemplate(MvcEvent $e): void
    {
        $model = $e->getResult();
        if (! $model instanceof ViewModel) {
            return;
        }

        $template = $model->getTemplate();
        if (! empty($template)) {
            return;
        }

        $routeMatch = $e->getRouteMatch();
        if ($preferRouteMatchController = $routeMatch->getParam(name: 'prefer_route_match_controller', default: false)) {
            $this->setPreferRouteMatchController(preferRouteMatchController: $preferRouteMatchController);
        }

        $controller = $e->getTarget();
        if (is_object(value: $controller)) {
            $controller = $controller::class;
        }

        $routeMatchController = $routeMatch->getParam(name: 'controller', default: '');
        if (! $controller || ($this->preferRouteMatchController && $routeMatchController)) {
            $controller = $routeMatchController;
        }

        $template = $this->mapController(controller: $controller);

        $action = $routeMatch->getParam(name: 'action');
        if (null !== $action) {
            $template .= '/' . $this->inflectName(name: $action);
        }
        $model->setTemplate(template: $template);
    }

    /**
     * Set map of controller namespace -> template pairs
     *
     * @return self
     */
    public function setControllerMap(array $map): static
    {
        krsort(array: $map);
        $this->controllerMap = $map;
        return $this;
    }

    /**
     * Maps controller to template if controller namespace is whitelisted or mapped
     *
     * @param string $controller controller FQCN
     * @return string|false template name or false if controller was not matched
     */
    public function mapController(string $controller): false|string
    {
        $mapped = '';
        foreach ($this->controllerMap as $namespace => $replacement) {
            if (
                // Allow disabling rule by setting value to false since config
                // merging have no feature to remove entries
                $replacement === false
                // Match full class or full namespace
                || ! ($controller === $namespace || str_starts_with(haystack: $controller, needle: $namespace . '\\'))
            ) {
                continue;
            }

            // Map namespace to $replacement if its value is string
            if (is_string(value: $replacement)) {
                $mapped     = rtrim(string: $replacement, characters: '/') . '/';
                $controller = substr(string: $controller, offset: strlen(string: $namespace) + 1) ?: '';
                break;
            }
        }

        //strip Controller namespace(s) (but not classname)
        $parts = explode(separator: '\\', string: $controller);
        array_pop(array: $parts);
        $parts = array_diff($parts, ['Controller']);
        //strip trailing Controller in class name
        $parts[]    = $this->deriveControllerClass(controller: $controller);
        $controller = implode(separator: '/', array: $parts);

        $template = trim(string: $mapped . $controller, characters: '/');

        // inflect CamelCase to dash
        return $this->inflectName(name: $template);
    }

    /**
     * Inflect a name to a normalized value
     *
     * Inlines the logic from jield-filter's Word\CamelCaseToDash filter.
     *
     * @param string $name
     * @return string
     */
    protected function inflectName(string $name): string
    {
        if (StringUtils::hasPcreUnicodeSupport()) {
            $pattern     = ['#(?<=(?:\p{Lu}))(\p{Lu}\p{Ll})#', '#(?<=(?:\p{Ll}|\p{Nd}))(\p{Lu})#'];
            $replacement = ['-\1', '-\1'];
        } else {
            $pattern     = ['#(?<=(?:[A-Z]))([A-Z]+)([A-Z][a-z])#', '#(?<=(?:[a-z0-9]))([A-Z])#'];
            $replacement = ['\1-\2', '-\1'];
        }

        $name = preg_replace(pattern: $pattern, replacement: $replacement, subject: $name);
        return strtolower(string: $name);
    }

    /**
     * Determine the name of the controller
     *
     * Strip the namespace, and the suffix "Controller" if present.
     *
     * @param string $controller
     * @return string
     */
    protected function deriveControllerClass(string $controller): string
    {
        if (str_contains(haystack: $controller, needle: '\\')) {
            $controller = substr(string: $controller, offset: strrpos(haystack: $controller, needle: '\\') + 1);
        }

        if (
            (10 < strlen(string: $controller))
            && (str_ends_with(haystack: $controller, needle: 'Controller'))
        ) {
            $controller = substr(string: $controller, offset: 0, length: -10);
        }

        return $controller;
    }

    /**
     * Sets the flag to instruct the listener to prefer the route match controller param
     * over the class name
     *
     * @param boolean $preferRouteMatchController
     */
    public function setPreferRouteMatchController(bool $preferRouteMatchController): void
    {
        $this->preferRouteMatchController = $preferRouteMatchController;
    }

    /**
     * @return boolean
     */
    public function isPreferRouteMatchController(): bool
    {
        return $this->preferRouteMatchController;
    }
}
