<?php

declare(strict_types=1);

namespace Jield\Test\PHPUnit;

use Jield\Test\PHPUnit\Controller\AbstractControllerTestCase;
use Throwable;

/** Add the application's exception chain to a controller assertion failure. */
final class FailureMessage
{
    public static function format(AbstractControllerTestCase $test, string $message): string
    {
        if (!$test->getTraceError()) {
            return $message;
        }

        /** @var mixed $exception */
        $exception = $test->getApplication()->getMvcEvent()->getParam('exception');
        if (!$exception instanceof Throwable) {
            return $message;
        }

        $messages = [];
        do {
            $messages[] = sprintf(
                "Exception '%s' with message '%s' in %s:%d",
                $exception::class,
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
            );
        } while ($exception = $exception->getPrevious());

        return sprintf("%s\n\nExceptions raised:\n%s\n", $message, implode("\n\n", $messages));
    }
}
