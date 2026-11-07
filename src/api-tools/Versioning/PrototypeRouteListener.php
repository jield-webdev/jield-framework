<?php

declare(strict_types=1);

namespace Jield\ApiTools\Versioning;

use Jield\ModuleManager\Listener\ConfigListener;
use Jield\ModuleManager\ModuleEvent;
use Jield\Stdlib\Value;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;
use Laminas\EventManager\ListenerAggregateTrait;
use Laminas\Stdlib\ArrayUtils;
use Override;

use function array_shift;
use function explode;
use function in_array;
use function is_array;
use function is_scalar;
use function strstr;

// Preserve the existing public API and the state and operations that implement it.
// @mago-expect lint:cyclomatic-complexity(1)
class PrototypeRouteListener implements ListenerAggregateInterface
{
    use ListenerAggregateTrait;

    /**
     * Match to prepend to versioned routes.
     */
    protected string $versionRoutePrefix = '[/v:version]';

    /**
     * Constraints to introduce in versioned routes
     */
    protected array $versionRouteOptions = [
        'defaults' => [
            'version' => 1,
        ],
        'constraints' => [
            'version' => '\d+',
        ],
    ];

    /**
     * Attach listener to ModuleEvent::EVENT_MERGE_CONFIG
     *
     * @param int $priority
     */
    #[Override]
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(
            eventName: ModuleEvent::EVENT_MERGE_CONFIG,
            listener: $this->onMergeConfig(...),
        );
    }

    /**
     * Listen to ModuleEvent::EVENT_MERGE_CONFIG
     *
     * Looks for api-tools-versioning.url and router configuration; if both present,
     * injects the route prototype and adds a chain route to each route listed
     * in the api-tools-versioning.url array.
     */
    // Keep the coordinated parsing or rendering steps of this compatibility method together.
    // @mago-expect lint:halstead(1)
    public function onMergeConfig(ModuleEvent $e): void
    {
        $configListener = $e->getConfigListener();
        if (!$configListener instanceof ConfigListener) {
            return;
        }

        /** @mago-var mixed $config */
        $config = $configListener->getMergedConfig(returnConfigAsObject: false);

        // Check for config keys
        if (($config['api-tools-versioning'] ?? null) === null || ($config['router'] ?? null) === null) {
            return;
        }

        // Do we need to inject a prototype?
        if (
            ($config['api-tools-versioning']['uri'] ?? null) === null
            || !is_array(value: Value::accessible($config['api-tools-versioning'])['uri'])
            || !($config['api-tools-versioning']['uri'] ?? null)
        ) {
            return;
        }

        // Override default version of 1 with user-specified config value, if available.
        if (
            ($config['api-tools-versioning']['default_version'] ?? null) !== null
            && is_scalar(value: $config['api-tools-versioning']['default_version'])
        ) {
            Value::setPath(
                $this->versionRouteOptions,
                ['defaults', 'version'],
                $config['api-tools-versioning']['default_version'],
            );
        }

        // Pre-process route list to strip out duplicates (often a result of
        // specifying nested routes)
        $routes = Value::strings($config['api-tools-versioning']['uri']);
        $filtered = [];
        foreach ($routes as $route) {
            if (strstr(haystack: $route, needle: '/')) {
                $temp = explode(separator: '/', string: $route, limit: 2);
                $route = array_shift(array: $temp);
            }

            if (in_array(needle: $route, haystack: $filtered, strict: true)) {
                continue;
            }

            $filtered[] = $route;
        }

        $routes = $filtered;

        // Inject chained routes
        foreach ($routes as $routeName) {
            if (($config['router']['routes'][$routeName] ?? null) === null) {
                continue;
            }

            if (
                !str_contains(
                    haystack: (string) Value::path($config, [
                        'router',
                        'routes',
                        $routeName,
                        'options',
                        'route',
                    ]),
                    needle: $this->versionRoutePrefix,
                )
            ) {
                Value::setPath(
                    $config,
                    ['router', 'routes', $routeName, 'options', 'route'],
                    $this->versionRoutePrefix
                        . Value::stringify(Value::path($config, [
                            'router',
                            'routes',
                            $routeName,
                            'options',
                            'route',
                        ])),
                );
            }

            $routeVersion = $this->versionRouteOptions;
            if (($config['api-tools-versioning']['default_version'][$routeName] ?? null) !== null) {
                Value::setPath(
                    $routeVersion,
                    ['defaults', 'version'],
                    $config['api-tools-versioning']['default_version'][$routeName],
                );
            }

            Value::setPath(
                $config,
                ['router', 'routes', $routeName, 'options'],
                ArrayUtils::merge(
                    a: \Webmozart\Assert\Assert::isArray(
                        Value::accessible(Value::path($config, [
                            'router',
                            'routes',
                            $routeName,
                        ]))['options'],
                    ),
                    b: $routeVersion,
                ),
            );
        }

        // Reset merged config
        $configListener->setMergedConfig(config: $config);
    }
}
