<?php

declare(strict_types=1);

namespace Jield\Stdlib\StringWrapper;

use Jield\Stdlib\Exception;
use Jield\Stdlib\StringUtils;

use function floor;
use function in_array;
use function sprintf;
use function str_pad;
use function str_repeat;
use function strtoupper;
use function wordwrap;

use const STR_PAD_BOTH;
use const STR_PAD_LEFT;
use const STR_PAD_RIGHT;

abstract class AbstractStringWrapper implements StringWrapperInterface
{
    /**
     * The character encoding working on
     *
     * @var string|null
     */
    protected ?string $encoding = 'UTF-8';

    /**
     * An optionally character encoding to convert to
     *
     * @var string|null
     */
    protected ?string $convertEncoding;

    /**
     * Check if the given character encoding is supported by this wrapper
     * and the character encoding to convert to is also supported.
     *
     * @param string $encoding
     * @param string|null $convertEncoding
     * @return bool
     */
    public static function isSupported(string $encoding, ?string $convertEncoding = null): bool
    {
        $supportedEncodings = static::getSupportedEncodings();

        if (! in_array(needle: strtoupper(string: $encoding), haystack: $supportedEncodings)) {
            return false;
        }

        if ($convertEncoding !== null && ! in_array(needle: strtoupper(string: $convertEncoding), haystack: $supportedEncodings)) {
            return false;
        }

        return true;
    }

    /**
     * Set character encoding working with and convert to
     *
     * @param string $encoding         The character encoding to work with
     * @param string|null $convertEncoding  The character encoding to convert to
     * @return StringWrapperInterface
     */
    public function setEncoding(string $encoding, ?string $convertEncoding = null): StringWrapperInterface
    {
        $supportedEncodings = static::getSupportedEncodings();

        $encodingUpper = strtoupper(string: $encoding);
        if (! in_array(needle: $encodingUpper, haystack: $supportedEncodings)) {
            throw new Exception\InvalidArgumentException(
                message: 'Wrapper doesn\'t support character encoding "' . $encoding . '"'
            );
        }

        if ($convertEncoding !== null) {
            $convertEncodingUpper = strtoupper(string: $convertEncoding);
            if (! in_array(needle: $convertEncodingUpper, haystack: $supportedEncodings)) {
                throw new Exception\InvalidArgumentException(
                    message: 'Wrapper doesn\'t support character encoding "' . $convertEncoding . '"'
                );
            }

            $this->convertEncoding = $convertEncodingUpper;
        } else {
            $this->convertEncoding = null;
        }
        $this->encoding = $encodingUpper;

        return $this;
    }

    /**
     * Get the defined character encoding to work with
     *
     * @return null|string
     * @throws Exception\LogicException If no encoding was defined.
     */
    public function getEncoding(): ?string
    {
        return $this->encoding;
    }

    /**
     * Get the defined character encoding to convert to
     *
     * @return string|null
     */
    public function getConvertEncoding(): ?string
    {
        return $this->convertEncoding;
    }

    /**
     * Convert a string from defined character encoding to the defined convert encoding
     *
     * @param string $str
     * @param bool $reverse
     * @return string|false
     */
    public function convert(string $str, bool $reverse = false): false|string
    {
        $encoding        = $this->getEncoding();
        $convertEncoding = $this->getConvertEncoding();
        if ($convertEncoding === null) {
            throw new Exception\LogicException(
                message: 'No convert encoding defined'
            );
        }

        if ($encoding === $convertEncoding) {
            return $str;
        }

        $from = $reverse ? $convertEncoding : $encoding;
        $to   = $reverse ? $encoding : $convertEncoding;
        throw new Exception\RuntimeException(message: sprintf(
            'Converting from "%s" to "%s" isn\'t supported by this string wrapper',
            $from ?? '',
            $to ?? ''
        ));
    }

    /**
     * Wraps a string to a given number of characters
     *
     * @param string $string
     * @param int $width
     * @param string $break
     * @param bool $cut
     * @return string
     */
    public function wordWrap(string $string, int $width = 75, string $break = "\n", bool $cut = false): string
    {
        $string = $string;
        if ($string === '') {
            return '';
        }

        $break = $break;
        if ($break === '') {
            throw new Exception\InvalidArgumentException(message: 'Break string cannot be empty');
        }

        $width = $width;
        if ($width === 0 && $cut) {
            throw new Exception\InvalidArgumentException(message: 'Cannot force cut when width is zero');
        }

        if (null === $this->getEncoding() || StringUtils::isSingleByteEncoding(encoding: $this->getEncoding())) {
            return wordwrap(string: $string, width: $width, break: $break, cut_long_words: $cut);
        }

        $stringWidth = $this->strlen(str: $string);
        $breakWidth  = $this->strlen(str: $break);

        $result    = '';
        $lastStart = $lastSpace = 0;

        for ($current = 0; $current < $stringWidth; $current++) {
            $char = $this->substr(str: $string, offset: $current, length: 1);

            $possibleBreak = $char;
            if ($breakWidth !== 1) {
                $possibleBreak = $this->substr(str: $string, offset: $current, length: $breakWidth);
            }

            if ($possibleBreak === $break) {
                $result   .= $this->substr(str: $string, offset: $lastStart, length: $current - $lastStart + $breakWidth);
                $current  += $breakWidth - 1;
                $lastStart = $lastSpace = $current + 1;
                continue;
            }

            if ($char === ' ') {
                if ($current - $lastStart >= $width) {
                    $result   .= $this->substr(str: $string, offset: $lastStart, length: $current - $lastStart) . $break;
                    $lastStart = $current + 1;
                }

                $lastSpace = $current;
                continue;
            }

            if ($current - $lastStart >= $width && $cut && $lastStart >= $lastSpace) {
                $result   .= $this->substr(str: $string, offset: $lastStart, length: $current - $lastStart) . $break;
                $lastStart = $lastSpace = $current;
                continue;
            }

            if ($current - $lastStart >= $width && $lastStart < $lastSpace) {
                $result   .= $this->substr(str: $string, offset: $lastStart, length: $lastSpace - $lastStart) . $break;
                $lastStart = $lastSpace += 1;
                continue;
            }
        }

        if ($lastStart !== $current) {
            $result .= $this->substr(str: $string, offset: $lastStart, length: $current - $lastStart);
        }

        return $result;
    }

    /**
     * Pad a string to a certain length with another string
     *
     * @param string $input
     * @param int $padLength
     * @param string $padString
     * @param int $padType
     * @return string
     */
    public function strPad(string $input, int $padLength, string $padString = ' ', int $padType = STR_PAD_RIGHT): string
    {
        if (null === $this->getEncoding() || StringUtils::isSingleByteEncoding(encoding: $this->getEncoding())) {
            return str_pad(string: $input, length: $padLength, pad_string: $padString, pad_type: $padType);
        }

        $lengthOfPadding = $padLength - $this->strlen(str: $input);
        if ($lengthOfPadding <= 0) {
            return $input;
        }

        $padStringLength = $this->strlen(str: $padString);
        if ($padStringLength === 0) {
            return $input;
        }

        $repeatCount = (int) floor(num: $lengthOfPadding / $padStringLength);

        if ($padType === STR_PAD_BOTH) {
            $repeatCountLeft = $repeatCountRight = ($repeatCount - $repeatCount % 2) / 2;

            $lastStringLength       = $lengthOfPadding - 2 * $repeatCountLeft * $padStringLength;
            $lastStringLeftLength   = $lastStringRightLength = (int) floor(num: $lastStringLength / 2);
            $lastStringRightLength += $lastStringLength % 2;

            $lastStringLeft  = $this->substr(str: $padString, offset: 0, length: $lastStringLeftLength);
            $lastStringRight = $this->substr(str: $padString, offset: 0, length: $lastStringRightLength);

            return str_repeat(string: $padString, times: $repeatCountLeft) . $lastStringLeft
                . $input
                . str_repeat(string: $padString, times: $repeatCountRight) . $lastStringRight;
        }

        $lastString = $this->substr(str: $padString, offset: 0, length: $lengthOfPadding % $padStringLength);

        if ($padType === STR_PAD_LEFT) {
            return str_repeat(string: $padString, times: $repeatCount) . $lastString . $input;
        }

        return $input . str_repeat(string: $padString, times: $repeatCount) . $lastString;
    }
}
