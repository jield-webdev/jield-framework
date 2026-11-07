<?php

declare(strict_types=1);

namespace Jield\ErrorHandler\Handler;

use ErrorException;
use InvalidArgumentException;
use Jield\ErrorHandler\HeroConstant;
use Laminas\Http\Header\Cookie;
use Laminas\Http\PhpEnvironment\RemoteAddress;
use Laminas\Http\PhpEnvironment\Request as HttpRequest;
use Laminas\Stdlib\RequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RuntimeException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Throwable;
use Webmozart\Assert\Assert;

use function basename;
use function get_current_user;
use function getcwd;
use function implode;
use function php_uname;
use function str_replace;

use const PHP_BINARY;
use const PHP_EOL;

final class Logging
{
    /** @var array<array-key, string> */
    private array $emailReceivers = [];

    private readonly string $emailSender;

    private const string PRIORITY = 'priority';

    private const string ERROR_TYPE = 'errorType';

    private const string ERROR_FILE = 'errorFile';

    private const string ERROR_LINE = 'errorLine';

    private const string TRACE = 'trace';

    private const string ERROR_MESSAGE = 'errorMessage';

    private const string SERVER_URL = 'server_url';

    public function __construct(
        private readonly LoggerInterface $psrLoggerAdapter,
        array $errorHeroModuleLocalConfig,
        private readonly ?MailerInterface $mailer = null,
        private readonly bool $includeFilesToAttachments = true,
    ) {
        $this->emailReceivers = \Jield\Stdlib\Value::strings(\Jield\Stdlib\Value::accessible($errorHeroModuleLocalConfig['email-notification-settings'])['email-to-send']);
        $this->emailSender = Assert::string(\Jield\Stdlib\Value::accessible($errorHeroModuleLocalConfig['email-notification-settings'])['email-from']);
    }

    /**
     * @return array<string, mixed>
     */
    private function getRequestData(?RequestInterface $request): array
    {
        if (!$request instanceof HttpRequest) {
            return [];
        }

        $query = $request->getQuery();
        $post = $request->getPost();
        $files = $request->getFiles();

        $content = $request->getContent();

        $queryData = $query->toArray();
        $requestMethod = $request->getMethod();
        $bodyData = $post->toArray();
        $rawData = str_replace(PHP_EOL, '', $content);
        $filesData = $this->includeFilesToAttachments ? $files->toArray() : [];

        $cookie = $request->getCookie();
        $cookieData = $cookie instanceof Cookie ? $cookie->getArrayCopy() : [];
        $ipAddress = new RemoteAddress()->getIpAddress();

        return [
            'request_method' => $requestMethod,
            'query_data' => $queryData,
            'body_data' => $bodyData,
            'raw_data' => $rawData,
            'files_data' => $filesData,
            'cookie_data' => $cookieData,
            'ip_address' => $ipAddress,
        ];
    }

    /**
     * @return array{
     *      priority: string,
     *      errorType: string,
     *      errorFile: string,
     *      errorLine: int,
     *      trace: string,
     *      errorMessage: string
     *  }
     */
    private function collectErrorExceptionData(Throwable $throwable): array
    {
        if ($throwable instanceof ErrorException) {
            //We need to use the new PSR7 severity level, these can be fetched
            //From the psrPriorityMap in this class

            $priority = Logging::getPsrPrioryFromSeverity($throwable->getSeverity());
            $errorType = HeroConstant::ERROR_TYPE[$throwable->getSeverity()];
        } else {
            $priority = LogLevel::ERROR;
            $errorType = $throwable::class;
        }

        $errorFile = $throwable->getFile();
        $errorLine = $throwable->getLine();
        $traceAsString = $throwable->getTraceAsString();
        $errorMessage = $throwable->getMessage();

        return [
            self::PRIORITY => $priority,
            self::ERROR_TYPE => $errorType,
            self::ERROR_FILE => $errorFile,
            self::ERROR_LINE => $errorLine,
            self::TRACE => $traceAsString,
            self::ERROR_MESSAGE => $errorMessage,
        ];
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function getPsrPrioryFromSeverity(int $severity, bool $fromLegacy = true): string
    {
        if ($fromLegacy) {
            return match ($severity) {
                E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR, E_PARSE => LogLevel::ERROR,
                E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING => LogLevel::WARNING,
                E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED => LogLevel::NOTICE,
                default => throw new InvalidArgumentException('Invalid severity level: ' . $severity),
            };
        }

        return match ($severity) {
            0 => LogLevel::EMERGENCY,
            1 => LogLevel::ALERT,
            2 => LogLevel::CRITICAL,
            3 => LogLevel::ERROR,
            4 => LogLevel::WARNING,
            5 => LogLevel::NOTICE,
            6 => LogLevel::INFO,
            7 => LogLevel::DEBUG,
            default => throw new InvalidArgumentException('Invalid severity level: ' . $severity),
        };
    }

    /**
     * @return array{
     *      server_url: string,
     *      url: string,
     *      file: string,
     *      line: int,
     *      error_type: string,
     *      trace: string,
     *      request_data: array<string, mixed>
     * }
     */
    private function collectErrorExceptionExtraData(array $collectedExceptionData, ?RequestInterface $request): array
    {
        if (!$request instanceof HttpRequest) {
            $argv = $_SERVER['argv'] ?? [];
            $serverUrl = php_uname('n');
            $url = $serverUrl . ':' . basename((string) getcwd()) . ' ' . get_current_user() . '$ ' . PHP_BINARY;

            $params = implode(' ', $argv);
            $url .= $params;
        } else {
            $http = $request->getUri();
            $serverUrl = (string) $http->getScheme() . '://' . (string) $http->getHost();
            $url = $http->toString();
        }

        return [
            self::SERVER_URL => $serverUrl,
            'url' => $url,
            'file' => Assert::string($collectedExceptionData[self::ERROR_FILE]),
            'line' => Assert::integer($collectedExceptionData[self::ERROR_LINE]),
            'error_type' => Assert::string($collectedExceptionData[self::ERROR_TYPE]),
            self::TRACE => Assert::string($collectedExceptionData[self::TRACE]),
            'request_data' => $this->getRequestData($request),
        ];
    }

    private function isExists(
        string $errorFile,
        int $errorLine,
        string $errorMessage,
        string $url,
        string $errorType,
    ): bool {
        return false;
    }

    /**
     * @throws \JsonException
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface
     */
    private function sendMail(string $priority, string $errorMessage, array $extra, string $subject): void
    {
        if ($this->mailer === null || $this->emailReceivers === []) {
            return;
        }
        $email = new Email()
            ->from($this->emailSender)
            ->to(...$this->emailReceivers)
            ->subject($subject)
            ->text($errorMessage . PHP_EOL . json_encode($extra, JSON_THROW_ON_ERROR));
        $this->mailer->send($email);
    }

    /**
     * @throws \JsonException
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface
     */
    public function handleErrorException(Throwable $throwable, ?RequestInterface $request = null): void
    {
        $collectedExceptionData = $this->collectErrorExceptionData($throwable);
        /**
         * @var array{url: string, server_url: string, mixed} $extra
         */
        $extra = $this->collectErrorExceptionExtraData($collectedExceptionData, $request);
        $serverUrl = $extra[self::SERVER_URL];

        try {
            if ($this->isExists(
                $collectedExceptionData[self::ERROR_FILE],
                $collectedExceptionData[self::ERROR_LINE],
                $collectedExceptionData[self::ERROR_MESSAGE],
                $extra['url'],
                $collectedExceptionData[self::ERROR_TYPE],
            )) {
                return;
            }

            unset($extra[self::SERVER_URL]);

            $this->psrLoggerAdapter->log(
                $collectedExceptionData[self::PRIORITY],
                $collectedExceptionData[self::ERROR_MESSAGE],
                $extra,
            );
        } catch (RuntimeException $runtimeException) {
            $collectedExceptionData = $this->collectErrorExceptionData($runtimeException);
            $extra = $this->collectErrorExceptionExtraData($collectedExceptionData, $request);
            unset($extra[self::SERVER_URL]);
        }

        $this->sendMail(
            $collectedExceptionData[self::PRIORITY],
            $collectedExceptionData[self::ERROR_MESSAGE],
            $extra,
            '[' . $serverUrl . '] ' . $collectedExceptionData[self::ERROR_TYPE] . ' has thrown',
        );
    }
}
