<?php

declare(strict_types=1);

namespace Jield\Mvc\View\Http;

use Jield\Mvc\MvcEvent;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface as Events;
use Laminas\Stdlib\StringUtils;
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
        $this->listeners[] = $events->attach(MvcEvent::EVENT_DISPATCH, [$this, 'injectTemplate'], -90);
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
        /** @var mixed $model */
        $model = $e->getResult();
        if (!$model instanceof ViewModel) {
            return;
        }

        $template = $model->getTemplate();
        if (!empty($template)) {
            return;
        }

        $routeMatch = $e->getRouteMatch();
        /** @var mixed $preferRouteMatchController */
        if ($preferRouteMatchController = \Jield\Stdlib\Value::present($routeMatch)->getParam('prefer_route_match_controller', false)) {
            $this->setPreferRouteMatchController(\Jield\Stdlib\Value::bool($preferRouteMatchController));
        }

        $controller = $e->getTarget();
        if (is_object($controller)) {
            $controller = $controller::class;
        }

        /** @var mixed $routeMatchController */
        $routeMatchController = \Jield\Stdlib\Value::present($routeMatch)->getParam('controller', '');
        if (!$controller || $this->preferRouteMatchController && \Jield\Stdlib\Value::truthy($routeMatchController)) {
            /** @var mixed $controller */
            $controller = $routeMatchController;
        }

        $template = $this->mapController(\Jield\Stdlib\Value::string($controller));

        /** @var mixed $action */
        $action = \Jield\Stdlib\Value::present($routeMatch)->getParam('action');
        if (null !== $action) {
            $template = (string) $template . '/' . $this->inflectName(\Jield\Stdlib\Value::string($action));
        }
        $model->setTemplate(\Jield\Stdlib\Value::nonEmptyString($template));
    }

    /**
     * Set map of controller namespace -> template pairs
     *
     * @return static
     */
    public function setControllerMap(array $map): static
    {
        krsort($map);
        $this->controllerMap = $map;
        return $this;
    }

    /**
     * Maps controller to template if controller namespace is whitelisted or mapped
     *
     * @param string $controller controller FQCN
     * @return string|false template name or false if controller was not matched
     */
    public function mapController($controller): false|string
    {
        $mapped = '';
        /** @var mixed $replacement */
        foreach ($this->controllerMap as $namespace => $replacement) {
            if (
                // Allow disabling rule by setting value to false since config
                // merging have no feature to remove entries
                $replacement === false
                // Match full class or full namespace
                || !($controller === $namespace || str_starts_with($controller, $namespace . '\\'))
            ) {
                continue;
            }

            // Map namespace to $replacement if its value is string
            if (is_string($replacement)) {
                $mapped = rtrim($replacement, '/') . '/';
                $controller = substr($controller, strlen(\Jield\Stdlib\Value::string($namespace)) + 1) ?: '';
                break;
            }
        }

        //strip Controller namespace(s) (but not classname)
        $parts = explode('\\', $controller);
        array_pop($parts);
        $parts = array_diff($parts, ['Controller']);
        //strip trailing Controller in class name
        $parts[] = $this->deriveControllerClass($controller);
        $controller = implode('/', $parts);

        $template = trim($mapped . $controller, '/');

        // inflect CamelCase to dash
        return $this->inflectName($template);
    }

    /**
     * Inflect a name to a normalized value
     *
     * Inlines the logic from laminas-filter's Word\CamelCaseToDash filter.
     *
     * @param  string $name
     * @return string
     */
    protected function inflectName($name): string
    {
        if (StringUtils::hasPcreUnicodeSupport()) {
            $pattern = ['#(?<=(?:\p{Lu}))(\p{Lu}\p{Ll})#', '#(?<=(?:\p{Ll}|\p{Nd}))(\p{Lu})#'];
            $replacement = ['-\1', '-\1'];
        } else {
            $pattern = ['#(?<=(?:[A-Z]))([A-Z]+)([A-Z][a-z])#', '#(?<=(?:[a-z0-9]))([A-Z])#'];
            $replacement = ['\1-\2', '-\1'];
        }

        $name = preg_replace($pattern, $replacement, $name);
        return strtolower(\Jield\Stdlib\Value::string($name));
    }

    /**
     * Determine the name of the controller
     *
     * Strip the namespace, and the suffix "Controller" if present.
     *
     * @param  string $controller
     * @return string
     */
    protected function deriveControllerClass($controller): string
    {
        if (str_contains($controller, '\\')) {
            $controller = substr($controller, (strrpos($controller, '\\') ?: -1) + 1);
        }

        if (10 < strlen($controller) && str_ends_with($controller, 'Controller')) {
            $controller = substr($controller, 0, -10);
        }

        return $controller;
    }

    /**
     * Sets the flag to instruct the listener to prefer the route match controller param
     * over the class name
     *
     * @param boolean $preferRouteMatchController
     */
    public function setPreferRouteMatchController($preferRouteMatchController): void
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
