<?php

declare(strict_types=1);

namespace Jield\ApiTools\ApiProblem;

use Exception;
use Jield\ApiTools\ApiProblem\Exception\InvalidArgumentException;
use Jield\ApiTools\ApiProblem\Exception\ProblemExceptionInterface;
use Throwable;

use function array_key_exists;
use function array_keys;
use function array_merge;
use function in_array;
use function is_numeric;
use function sprintf;
use function strtolower;
use function trim;

/**
 * Object describing an API-Problem payload.
 */
class ApiProblem
{
    /**
     * Content type for api problem response
     */
    public const string CONTENT_TYPE = 'application/problem+json';

    /**
     * Additional details to include in report.
     */
    protected array $additionalDetails = [];

    /**
     * URL describing the problem type; defaults to HTTP status codes.
     *
     * @var string
     */
    protected string $type = 'https://datatracker.ietf.org/doc/html/rfc7231#section-6';

    /**
     * Description of the specific problem.
     *
     * @var string|Exception|Throwable
     */
    protected mixed $detail = '';

    /**
     * Whether or not to include a stack trace and previous
     * exceptions when an exception is provided for the detail.
     */
    protected bool $detailIncludesStackTrace = false;

    /**
     * HTTP status for the error.
     */
    protected int $status;

    /**
     * Normalized property names for overloading.
     *
     * @var array
     */
    protected array $normalizedProperties = [
        'type' => 'type',
        'status' => 'status',
        'title' => 'title',
        'detail' => 'detail',
    ];

    /**
     * Status titles for common problems.
     *
     * @var array
     */
    protected array $problemStatusTitles = [
        // CLIENT ERROR
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Time-out',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Large',
        415 => 'Unsupported Media Type',
        416 => 'Requested range not satisfiable',
        417 => 'Expectation Failed',
        418 => "I'm a teapot",
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        // SERVER ERROR
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Time-out',
        505 => 'HTTP Version not supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        511 => 'Network Authentication Required',
    ];

    /**
     * Title of the error.
     */
    protected ?string $title = null;

    /**
     * Create an instance using the provided information. If nothing is
     * provided for the type field, the class default will be used;
     * if the status matches any known, the title field will be selected
     * from $problemStatusTitles as a result.
     */
    public function __construct(
        int|string $status,
        Throwable|Exception|string $detail,
        ?string $type = null,
        ?string $title = null,
        array $additional = [],
    ) {
        if ($detail instanceof ProblemExceptionInterface) {
            if (null === $type) {
                $type = $detail->getType();
            }

            if (null === $title) {
                $title = $detail->getTitle();
            }

            if ($additional === []) {
                $additional = $detail->getAdditionalDetails();
            }
        }

        // Ensure a valid HTTP status
        if (!is_numeric(value: $status) || $status < 100 || $status > 599) {
            $status = 500;
        }

        $this->status = (int) $status;
        $this->detail = $detail;
        $this->title = $title;

        if (null !== $type) {
            $this->type = $type;
        }

        $this->additionalDetails = $additional === null ? [] : (is_array($additional) ? $additional : iterator_to_array($additional));
    }

    /**
     * Retrieve properties.
     *
     * @param string $name
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function __get(string $name)
    {
        $normalized = strtolower(string: $name);
        if (in_array(needle: $normalized, haystack: array_keys(array: $this->normalizedProperties), strict: true)) {
            /** @var mixed $prop */
            $prop = $this->normalizedProperties[$normalized];

            return match ($prop) {
                'detail' => $this->detail, 'status' => $this->status,
                'title' => $this->title, 'type' => $this->type,
                default => null,
            };
        }

        if (isset($this->additionalDetails[$name])) {
            return $this->additionalDetails[$name];
        }

        if (isset($this->additionalDetails[$normalized])) {
            return $this->additionalDetails[$normalized];
        }

        throw new InvalidArgumentException(message: sprintf('Invalid property name "%s"', $name));
    }

    /**
     * Cast to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        $problem = [
            'type' => $this->type,
            'title' => $this->getTitle(),
            'status' => $this->getStatus(),
            'detail' => $this->getDetail(),
        ];
        // Required fields should always overwrite additional fields
        return array_merge($this->additionalDetails, $problem);
    }

    /**
     * Set the flag indicating whether an exception detail should include a
     * stack trace and previous exception information.
     *
     * @param bool $flag
     * @return static
     */
    public function setDetailIncludesStackTrace(bool $flag): static
    {
        $this->detailIncludesStackTrace = $flag;

        return $this;
    }

    /**
     * Retrieve the API-Problem detail.
     *
     * If an exception was provided, creates the detail message from it;
     * otherwise, detail as provided is used.
     *
     * @return string
     */
    protected function getDetail(): ProblemExceptionInterface|Throwable|Exception|string
    {
        if ($this->detail instanceof Throwable) {
            return $this->createDetailFromException();
        }

        return $this->detail;
    }

    /**
     * Retrieve the API-Problem HTTP status code.
     *
     * If an exception was provided, creates the status code from it;
     * otherwise, code as provided is used.
     */
    public function getStatus(): int
    {
        if ($this->detail instanceof Throwable) {
            $this->status = (int) $this->createStatusFromException();
        }

        return $this->status;
    }

    /**
     * Retrieve the title.
     *
     * If the default $type is used, and the $status is found in
     * $problemStatusTitles, then use the matching title.
     *
     * If no title was provided, and the above conditions are not met, use the
     * string 'Unknown'.
     *
     * Otherwise, use the title provided.
     *
     * @return string
     */
    public function getTitle(): ?string
    {
        if (null !== $this->title) {
            return $this->title;
        }

        if (
            $this->type === 'https://datatracker.ietf.org/doc/html/rfc7231#section-6'
            && array_key_exists(key: $this->getStatus(), array: $this->problemStatusTitles)
        ) {
            return \Jield\Stdlib\Value::nullableString($this->problemStatusTitles[$this->status]);
        }

        if ($this->detail instanceof Throwable) {
            return $this->detail::class;
        }

        return 'Unknown';
    }

    /**
     * Create detail message from an exception.
     *
     * @return string
     */
    protected function createDetailFromException(): string
    {
        /** @var Exception|Throwable $e */
        $e = $this->detail;

        if (!$this->detailIncludesStackTrace) {
            return $e->getMessage();
        }

        $message = trim(string: $e->getMessage());
        $this->additionalDetails['trace'] = $e->getTrace();

        $previous = [];
        $e = $e->getPrevious();
        while ($e) {
            $previous[] = [
                'code' => (int) $e->getCode(),
                'message' => trim(string: $e->getMessage()),
                'trace' => $e->getTrace(),
            ];
            $e = $e->getPrevious();
        }

        if ($previous !== []) {
            $this->additionalDetails['exception_stack'] = $previous;
        }

        return $message;
    }

    /**
     * Create HTTP status from an exception.
     *
     * @return int|string
     */
    protected function createStatusFromException(): int|string
    {
        /** @var Exception|Throwable $e */
        $e = $this->detail;
        $status = $e->getCode();

        if ($status) {
            return $status;
        }

        return 500;
    }
}
