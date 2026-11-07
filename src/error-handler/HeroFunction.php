<?php

declare(strict_types=1);

namespace Jield\ErrorHandler;

use ArrayLookup\AtLeast;
use Seld\JsonLint\JsonParser;
use Seld\JsonLint\ParsingException;
use Throwable;

use function is_array;
use function strip_tags;

// This exported function keeps its established camelCase name.
// @mago-expect lint:function-name(1)
function detectMessageContentType(string $message): string
{
    $jsonParser = new JsonParser();
    if (!$jsonParser->lint(input: $message) instanceof ParsingException) {
        return 'application/problem+json';
    }

    return strip_tags(string: $message) === $message ? 'text/plain' : 'text/html';
}

/**
 * @param array<array-key, mixed> $excludeExceptionsConfig
 */
// This exported function keeps its established camelCase name.
// @mago-expect lint:function-name(1)
function isExcludedException(array $excludeExceptionsConfig, Throwable $throwable): bool
{
    $exceptionOrErrorClass = $throwable::class;
    $message = $throwable->getMessage();

    /**
     * @param mixed $excludeExceptionConfig
     */
    $filter = static function (mixed $excludeExceptionConfig) use ($exceptionOrErrorClass, $message): bool {
        if ($excludeExceptionConfig === $exceptionOrErrorClass) {
            return true;
        }

        return (
            is_array(value: $excludeExceptionConfig)
            && $excludeExceptionConfig[0] === $exceptionOrErrorClass
            && $excludeExceptionConfig[1] === $message
        );
    };

    return AtLeast::once(data: $excludeExceptionsConfig, filter: $filter);
}
