<?php

namespace Jield\Http\Header;

use DateTime;
use DateTimeZone;
use Exception;
use Jield\Http\Header\Exception\InvalidArgumentException;
use function is_numeric;
use function is_string;
use function sprintf;
use function strtolower;
use function strtotime;

/**
 * Abstract Date/Time Header
 * Supports headers that have date/time as value
 *
 * @see \Jield\Http\Header\Date
 * @see \Jield\Http\Header\Expires
 * @see \Jield\Http\Header\IfModifiedSince
 * @see \Jield\Http\Header\IfUnmodifiedSince
 * @see \Jield\Http\Header\LastModified
 *
 * Note for 'Location' header:
 * While RFC 1945 requires an absolute URI, most of the browsers also support relative URI
 * This class allows relative URIs, and let user retrieve URI instance if strict validation needed
 */
abstract class AbstractDate implements HeaderInterface
{
    /**
     * Date formats according to RFC 2616
     *
     * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec3.html#sec3.3
     */
    public const int DATE_RFC1123 = 0;
    public const int DATE_RFC1036 = 1;
    public const int DATE_ANSIC = 2;

    /**
     * Date instance for this header
     *
     * @var DateTime
     */
    protected DateTime $date;

    /**
     * Date output format
     *
     * @var string
     */
    protected static string $dateFormat = 'D, d M Y H:i:s \G\M\T';

    /**
     * Date formats defined by RFC 2616. RFC 1123 date is required
     * RFC 1036 and ANSI C formats are provided for compatibility with old servers/clients
     *
     * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec3.html#sec3.3
     *
     * @var array
     */
    protected static array $dateFormats
        = [
            self::DATE_RFC1123 => 'D, d M Y H:i:s \G\M\T',
            self::DATE_RFC1036 => 'D, d M y H:i:s \G\M\T',
            self::DATE_ANSIC   => 'D M j H:i:s Y',
        ];

    /**
     * Create date-based header from string
     *
     * @param string $headerLine
     * @return static
     * @throws InvalidArgumentException
     */
    public static function fromString(string $headerLine): static
    {
        $dateHeader = new static();

        [$name, $date] = GenericHeader::splitHeaderLine(headerLine: $headerLine);

        // check to ensure proper header type for this factory
        if (strtolower(string: $name) !== strtolower(string: $dateHeader->getFieldName())) {
            throw new InvalidArgumentException(
                message: 'Invalid header line for "' . $dateHeader->getFieldName() . '" header string'
            );
        }

        $dateHeader->setDate(date: $date);

        return $dateHeader;
    }

    /**
     * Create date-based header from strtotime()-compatible string
     *
     * @param int|string $time
     * @return static
     * @throws InvalidArgumentException
     */
    public static function fromTimeString(int|string $time): static
    {
        return static::fromTimestamp(time: strtotime(datetime: $time));
    }

    /**
     * Create date-based header from Unix timestamp
     *
     * @param int $time
     * @return static
     * @throws InvalidArgumentException
     */
    public static function fromTimestamp(int $time): static
    {
        $dateHeader = new static();

        if (!$time || !is_numeric(value: $time)) {
            throw new InvalidArgumentException(
                message: 'Invalid time for "' . $dateHeader->getFieldName() . '" header string'
            );
        }

        $dateHeader->setDate(date: new DateTime(datetime: '@' . $time));

        return $dateHeader;
    }

    /**
     * Set date output format
     *
     * @param int $format
     * @throws InvalidArgumentException
     */
    public static function setDateFormat(int $format): void
    {
        if (!isset(static::$dateFormats[$format])) {
            throw new InvalidArgumentException(message: sprintf(
                'No constant defined for provided date format: %s',
                $format
            ));
        }

        static::$dateFormat = static::$dateFormats[$format];
    }

    /**
     * Return current date output format
     *
     * @return string
     */
    public static function getDateFormat(): string
    {
        return static::$dateFormat;
    }

    /**
     * Set the date for this header, this can be a string or an instance of \DateTime
     *
     * @param DateTime|string $date
     * @return $this
     * @throws InvalidArgumentException
     */
    public function setDate(DateTime|string $date): static
    {
        if (is_string(value: $date)) {
            try {
                $date = new DateTime(datetime: $date, timezone: new DateTimeZone(timezone: 'GMT'));
            } catch (Exception $e) {
                throw new InvalidArgumentException(
                    message: sprintf('Invalid date passed as string (%s)', $date),
                    code: $e->getCode(),
                    previous: $e
                );
            }
        } elseif (!$date instanceof DateTime) {
            throw new InvalidArgumentException(message: 'Date must be an instance of \DateTime or a string');
        }

        $date->setTimezone(timezone: new DateTimeZone(timezone: 'GMT'));
        $this->date = $date;

        return $this;
    }

    /**
     * Return date for this header
     *
     * @return string
     */
    public function getDate(): string
    {
        return $this->date()->format(format: static::$dateFormat);
    }

    /**
     * Return date for this header as an instance of \DateTime
     *
     * @return DateTime
     */
    public function date(): DateTime
    {
        if ($this->date === null) {
            $this->date = new DateTime(datetime: 'now', timezone: new DateTimeZone(timezone: 'GMT'));
        }
        return $this->date;
    }

    /**
     * Compare provided date to date for this header
     * Returns < 0 if date in header is less than $date; > 0 if it's greater, and 0 if they are equal.
     *
     * @param DateTime|string $date
     * @return int
     * @throws InvalidArgumentException
     * @see \strcmp()
     *
     */
    public function compareTo(DateTime|string $date): int
    {
        if (is_string(value: $date)) {
            try {
                $date = new DateTime(datetime: $date, timezone: new DateTimeZone(timezone: 'GMT'));
            } catch (Exception $e) {
                throw new InvalidArgumentException(
                    message: sprintf('Invalid Date passed as string (%s)', $date),
                    code: $e->getCode(),
                    previous: $e
                );
            }
        } elseif (!$date instanceof DateTime) {
            throw new InvalidArgumentException(message: 'Date must be an instance of \DateTime or a string');
        }

        $dateTimestamp = $date->getTimestamp();
        $thisTimestamp = $this->date()->getTimestamp();

        return $thisTimestamp === $dateTimestamp ? 0 : ($thisTimestamp > $dateTimestamp ? 1 : -1);
    }

    /**
     * Get header value as formatted date
     *
     * @return string
     */
    public function getFieldValue(): string
    {
        return $this->getDate();
    }

    /**
     * Return header line
     *
     * @return string
     */
    public function toString(): string
    {
        return $this->getFieldName() . ': ' . $this->getDate();
    }

    /**
     * Allow casting to string
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toString();
    }
}
