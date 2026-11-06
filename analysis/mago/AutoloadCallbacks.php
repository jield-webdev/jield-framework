<?php

// SPL ignores the return value of an autoloader. Legacy autoloaders commonly
// return the resolved class name, which PHP accepts alongside void callbacks.
/** @param (callable(class-string): mixed)|null $callback */
function spl_autoload_register(?callable $callback = null, bool $throw = true, bool $prepend = false): bool {}

/** @param callable(class-string): mixed $callback */
function spl_autoload_unregister(callable $callback): bool {}
