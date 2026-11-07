<?php

declare(strict_types=1);

namespace Jield\ErrorHandler;

use ArrayLookup\AtLeast;
use ErrorException;
use Jield\Mvc\MvcEvent;
use Jield\Stdlib\Value;
use Webmozart\Assert\Assert;

use function error_get_last;
use function error_reporting;
use function ini_set;
use function is_array;
use function ob_end_flush;
use function ob_get_clean;
use function ob_get_level;
use function ob_start;
use function register_shutdown_function;
use function set_error_handler;
use function str_starts_with;

use const E_ALL;

trait HeroTrait
{
    private string $result = '';

    private ?MvcEvent $mvcEvent = null;

    abstract protected function capturesErrorOutput(): bool;

    abstract protected function getErrorHandlerConfiguration(): array;

    abstract protected function handleShutdownException(ErrorException $exception): void;

    public function phpError(mixed ...$args): void
    {
        if (isset($args[0]) && $args[0] instanceof MvcEvent) {
            Assert::count(array: $args, number: 1);
            Assert::isInstanceOf(value: $args[0], class: MvcEvent::class);

            $this->mvcEvent = $args[0];
        }

        if (!Value::accessible($this->getErrorHandlerConfiguration()['display-settings'])['display_errors']) {
            error_reporting(error_level: E_ALL);
            ini_set(option: 'display_errors', value: '0');
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        ob_start(callback: [$this, 'phpFatalErrorHandler']);
        register_shutdown_function(callback: [$this, 'execOnShutdown']);
        set_error_handler(callback: [$this, 'phpErrorHandler']);
    }

    private static function isUncaught(string $message): bool
    {
        return str_starts_with(haystack: $message, needle: 'Uncaught');
    }

    public function phpFatalErrorHandler(string $buffer): string
    {
        $error = error_get_last();
        if ($error === null) {
            return $buffer;
        }

        return self::isUncaught(message: $error['message']) || $this->result === '' ? $buffer : $this->result;
    }

    /**
     * @throws \Laminas\View\Exception\RenderingFailedException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \JsonException
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface
     */
    public function execOnShutdown(): void
    {
        $error = error_get_last();
        if ($error === null) {
            return;
        }

        if (self::isUncaught(message: $error['message'])) {
            return;
        }

        $errorException = new ErrorException(
            message: $error['message'],
            code: 0,
            severity: $error['type'],
            filename: $error['file'],
            line: $error['line'],
        );

        ob_start();
        $this->handleShutdownException($errorException);
        $this->result = (string) ob_get_clean();
    }

    /**
     * @throws ErrorException When php error happen and error type is not excluded in the config.
     */
    public function phpErrorHandler(int $errorType, string $errorMessage, string $errorFile, int $errorLine): bool
    {
        if ((error_reporting() & $errorType) === 0) {
            return true;
        }

        $filter = static fn(mixed $excludePhpError): bool => (
            $errorType === $excludePhpError
            || is_array(value: $excludePhpError)
            && $excludePhpError[0] === $errorType
            && $excludePhpError[1] === $errorMessage
        );

        if (AtLeast::once(
            data: Assert::isArray(Value::path($this->getErrorHandlerConfiguration(), [
                'display-settings',
                'exclude-php-errors',
            ])),
            filter: $filter,
        )) {
            return true;
        }

        throw new ErrorException(
            message: $errorMessage,
            code: 0,
            severity: $errorType,
            filename: $errorFile,
            line: $errorLine,
        );
    }
}
