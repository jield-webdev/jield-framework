<?php

declare(strict_types=1);

namespace Jield\ErrorHandler;

use ArrayLookup\AtLeast;
use Seld\JsonLint\JsonParser;
use Seld\JsonLint\ParsingException;
use Throwable;

use function is_array;
use function strip_tags;

function detectMessageContentType(string $message): string
{
    $jsonParser = new JsonParser();
    return (
        $jsonParser->lint(input: $message) instanceof ParsingException
            ? (strip_tags(string: $message) === $message ? 'text/plain' : 'text/html')
            : 'application/problem+json'
    );
}

/**
 * @param array<array-key, mixed> $excludeExceptionsConfig
 */
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
