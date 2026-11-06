<?php

declare(strict_types=1);

$loader = require __DIR__ . '/vendor/autoload.php';
// The framework's dependency versions take precedence over application transitive dependencies.
$loader->unregister();
$loader->register(prepend: true);

if (!class_exists(\Laminas\Stdlib\SplPriorityQueue::class, false)) {
    class_alias(\Jield\Stdlib\SplPriorityQueue::class, \Laminas\Stdlib\SplPriorityQueue::class);
}

// Navigation still uses the mutable MVC route-match contract.
if (!class_exists(\Laminas\Router\RouteMatch::class, false)) {
    class_alias(\Jield\Mvc\Router\RouteMatch::class, \Laminas\Router\RouteMatch::class);
}
\Laminas\Navigation\Page\AbstractPage::addFactory(static function (array $options): ?\Laminas\Navigation\Page\AbstractPage {
    if (isset($options['route']) || isset($options['controller']) || isset($options['action'])) {
        return new \Jield\Mvc\Navigation\MvcPage($options);
    }
    return null;
});

return $loader;
