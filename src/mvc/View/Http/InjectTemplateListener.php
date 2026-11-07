<?php

declare(strict_types=1);

namespace Jield\Mvc\View\Http;

use Jield\Mvc\MvcEvent;
use Jield\Stdlib\Value;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface as Events;
use Laminas\Stdlib\StringUtils;
use Laminas\View\Model\ModelInterface as ViewModel;
use Webmozart\Assert\Assert;

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
     */
    protected array $controllerMap = [];

    /**
     * Flag to force the use of the route match controller param
     */
    protected bool $preferRouteMatchController = false;

    /**
     * {@inheritDoc}
     */
    public function attach(Events $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_DISPATCH, $this->injectTemplate(...), -90);
    }

    /**
     * Inject a template into the view model, if none present
     *
     * Template is derived from the controller found in the route match, and,
     * optionally, the action, if present.
     */
    public function injectTemplate(MvcEvent $e): void
    {
        /** @mago-var mixed $model */
        $model = $e->getResult();
        if (!$model instanceof ViewModel) {
            return;
        }

        $template = $model->getTemplate();
        if (!empty($template)) {
            return;
        }

        $routeMatch = $e->getRouteMatch();
        /** @mago-var mixed $preferRouteMatchController */
        if (
            $preferRouteMatchController = Assert::notNull($routeMatch)->getParam('prefer_route_match_controller', false)
        ) {
            $this->setPreferRouteMatchController(Assert::boolean($preferRouteMatchController));
        }

        $controller = $e->getTarget();
        if (is_object($controller)) {
            $controller = $controller::class;
        }

        /** @mago-var mixed $routeMatchController */
        $routeMatchController = Assert::notNull($routeMatch)->getParam('controller', '');
        if (!$controller || $this->preferRouteMatchController && Value::truthy($routeMatchController)) {
            /** @mago-var mixed $controller */
            $controller = $routeMatchController;
        }

        $template = $this->mapController(Assert::string($controller));

        /** @mago-var mixed $action */
        $action = Assert::notNull($routeMatch)->getParam('action');
        if (null !== $action) {
            $template = (string) $template . '/' . $this->inflectName(Assert::string($action));
        }
        $model->setTemplate(Assert::stringNotEmpty($template));
    }

    /**
     * Set map of controller namespace -> template pairs
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
        /** @mago-var mixed $replacement */
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
                $controller = substr($controller, strlen(Assert::string($namespace)) + 1) ?: '';
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
        return strtolower(Assert::string($name));
    }

    /**
     * Determine the name of the controller
     *
     * Strip the namespace, and the suffix "Controller" if present.
     *
     * @param  string $controller
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

    public function isPreferRouteMatchController(): bool
    {
        return $this->preferRouteMatchController;
    }
}
