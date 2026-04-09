<?php

namespace Jield\Http\Header;

use stdClass;
use function array_intersect;
use function array_shift;
use function array_walk;
use function count;
use function explode;
use function implode;
use function is_float;
use function is_int;
use function is_numeric;
use function is_string;
use function preg_match;
use function preg_match_all;
use function preg_replace_callback;
use function sprintf;
use function str_split;
use function stripslashes;
use function strlen;
use function strpos;
use function strtolower;
use function substr;
use function trim;
use function usort;
use function version_compare;

/**
 * Abstract Accept Header
 *
 * Naming conventions:
 *
 *    Accept: audio/mp3; q=0.2; version=0.5, audio/basic+mp3
 *   |------------------------------------------------------|  header line
 *   |------|                                                  field name
 *          |-----------------------------------------------|  field value
 *          |-------------------------------|                  field value part
 *          |------|                                           type
 *                  |--|                                       subtype
 *                  |--|                                       format
 *                                                |----|       subtype
 *                                                      |---|  format
 *                      |-------------------|                  parameter set
 *                              |-----------|                  parameter
 *                              |-----|                        parameter key
 *                                      |--|                   parameter value
 *                        |---|                                priority
 *
 * @see        http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.1
 */
abstract class AbstractAccept implements HeaderInterface
{
    /** @var stdClass[] */
    protected array $fieldValueParts = [];

    /** @var string */
    protected string $regexAddType;

    /**
     * Determines if since last mutation the stack was sorted
     *
     * @var bool
     */
    protected bool $sorted = false;

    /**
     * Parse a full header line or just the field value part.
     *
     * @param string $headerLine
     */
    public function parseHeaderLine(string $headerLine): void
    {
        if (strpos(haystack: $headerLine, needle: ':') !== false) {
            [$name, $value] = GenericHeader::splitHeaderLine(headerLine: $headerLine);
            if (strtolower(string: $name) !== strtolower(string: $this->getFieldName())) {
                $value = $headerLine; // This is just for preserve the BC.
            }
        } else {
            $value = $headerLine;
        }

        HeaderValue::assertValid(value: $value);

        foreach ($this->getFieldValuePartsFromHeaderLine(headerLine: $value) as $value) {
            $this->addFieldValuePartToQueue(value: $value);
        }
    }

    /**
     * Factory method: parse Accept header string
     *
     * @param string $headerLine
     * @return static
     */
    public static function fromString(string $headerLine): static
    {
        $obj = new static();
        $obj->parseHeaderLine(headerLine: $headerLine);
        return $obj;
    }

    /**
     * Parse the Field Value Parts represented by a header line
     *
     * @param string $headerLine
     * @return array
     * @throws Exception\InvalidArgumentException If header is invalid.
     */
    public function getFieldValuePartsFromHeaderLine(string $headerLine): array
    {
        // process multiple accept values, they may be between quotes
        if (
            !preg_match_all(pattern: '/(?:[^,"]|"(?:[^\\\"]|\\\.)*")+/', subject: $headerLine, matches: $values)
            || !isset($values[0])
        ) {
            throw new Exception\InvalidArgumentException(
                message: 'Invalid header line for ' . $this->getFieldName() . ' header string'
            );
        }

        $out = [];
        foreach ($values[0] as $value) {
            $value = trim(string: $value);
            $out[] = $this->parseFieldValuePart(fieldValuePart: $value);
        }

        return $out;
    }

    /**
     * Parse the accept params belonging to a media range
     *
     * @param string $fieldValuePart
     * @return stdClass
     */
    protected function parseFieldValuePart(string $fieldValuePart): object|stdClass
    {
        $raw = $subtypeWhole = $type = $fieldValuePart;
        if ($pos = strpos(haystack: $fieldValuePart, needle: ';')) {
            $type = substr(string: $fieldValuePart, offset: 0, length: $pos);
        }

        $params = $this->getParametersFromFieldValuePart(fieldValuePart: $fieldValuePart);

        if ($pos = strpos(haystack: $fieldValuePart, needle: ';')) {
            $fieldValuePart = trim(string: substr(string: $fieldValuePart, offset: 0, length: $pos));
        }

        $format  = '*';
        $subtype = '*';

        return (object)[
            'typeString' => trim(string: $fieldValuePart),
            'type'       => $type,
            'subtype'    => $subtype,
            'subtypeRaw' => $subtypeWhole,
            'format'     => $format,
            'priority'   => $params['q'] ?? 1,
            'params'     => $params,
            'raw'        => trim(string: $raw),
        ];
    }

    /**
     * Parse the keys contained in the header line
     *
     * @param string $fieldValuePart
     * @return array
     */
    protected function getParametersFromFieldValuePart(string $fieldValuePart): array
    {
        $params = [];
        if (($pos = strpos(haystack: $fieldValuePart, needle: ';')) !== false) {
            preg_match_all(pattern: '/(?:[^;"]|"(?:[^\\\"]|\\\.)*")+/', subject: $fieldValuePart, matches: $paramsStrings);

            if (isset($paramsStrings[0])) {
                array_shift(array: $paramsStrings[0]);
                $paramsStrings = $paramsStrings[0];
            }

            foreach ($paramsStrings as $param) {
                $explode = explode(separator: '=', string: $param, limit: 2);

                if (count(value: $explode) === 2) {
                    $value = trim(string: $explode[1]);
                } else {
                    $value = null;
                }

                if (isset($value[0]) && $value[0] === '"' && substr(string: $value, offset: -1) === '"') {
                    $value = substr(string: substr(string: $value, offset: 1), offset: 0, length: -1);
                }

                $params[trim(string: $explode[0])] = stripslashes(string: $value ?? '');
            }
        }

        return $params;
    }

    /**
     * Get field value
     *
     * @param array|null $values
     * @return string
     */
    public function getFieldValue(?array $values = null): string
    {
        if ($values === null) {
            return $this->getFieldValue(values: $this->fieldValueParts);
        }

        $strings = [];
        foreach ($values as $value) {
            $params = $value->params;
            array_walk(array: $params, callback: [$this, 'assembleAcceptParam']);
            $strings[] = implode(separator: ';', array: [$value->typeString] + $params);
        }

        return implode(separator: ', ', array: $strings);
    }

    /**
     * Assemble and escape the field value parameters based on RFC 2616 section 2.1
     *
     * @param string $value
     * @param string $key
     * @return string
     * @todo someone should review this thoroughly
     */
    protected function assembleAcceptParam(string &$value, string $key): string
    {
        $separators = ['(', ')', '<', '>', '@', ',', ';', ':', '/', '[', ']', '?', '=', '{', '}', ' ', "\t"];

        $escaped = preg_replace_callback(
            pattern: '/[[:cntrl:]"\\\\]/', // escape cntrl, ", \
            callback: function ($v) {
                return '\\' . $v[0];
            },
            subject: $value
        );

        if ($escaped === $value && !array_intersect(str_split(string: $value), $separators)) {
            $value = $key . ($value ? '=' . $value : '');
        } else {
            $value = $key . ($value ? '="' . $escaped . '"' : '');
        }

        return $value;
    }

    /**
     * Add a type, with the given priority
     *
     * @param string $type
     * @param float|int $priority
     * @param array (optional) $params
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    protected function addType(string $type, float|int $priority = 1, array $params = []): static
    {
        if (!preg_match(pattern: $this->regexAddType, subject: $type)) {
            throw new Exception\InvalidArgumentException(message: sprintf(
                '%s expects a valid type; received "%s"',
                __METHOD__,
                $type
            ));
        }

        if (
            !is_int(value: $priority) && !is_float(value: $priority) && !is_numeric(value: $priority)
            || $priority > 1 || $priority < 0
        ) {
            throw new Exception\InvalidArgumentException(message: sprintf(
                '%s expects a numeric priority; received %s',
                __METHOD__,
                (string)$priority
            ));
        }

        if ($priority !== 1) {
            $params = ['q' => sprintf('%01.1f', $priority)] + $params;
        }

        $assembledString = $this->getFieldValue(
            values: [(object)['typeString' => $type, 'params' => $params]]
        );

        $value = $this->parseFieldValuePart(fieldValuePart: $assembledString);
        $this->addFieldValuePartToQueue(value: $value);
        return $this;
    }

    /**
     * Does the header have the requested type?
     *
     * @param array|string $matchAgainst
     * @return bool
     */
    protected function hasType(array|string $matchAgainst): bool
    {
        return (bool)$this->match(matchAgainst: $matchAgainst);
    }

    /**
     * Match a media string against this header
     *
     * @param array|string $matchAgainst
     * @return Accept\FieldValuePart\AcceptFieldValuePart|bool The matched value or false
     */
    public function match(array|string $matchAgainst): bool|Accept\FieldValuePart\AcceptFieldValuePart|stdClass
    {
        if (is_string(value: $matchAgainst)) {
            $matchAgainst = $this->getFieldValuePartsFromHeaderLine(headerLine: $matchAgainst);
        }

        foreach ($this->getPrioritized() as $left) {
            foreach ($matchAgainst as $right) {
                if ($right->type === '*' || $left->type === '*') {
                    if ($this->matchAcceptParams(match1: $left, match2: $right)) {
                        $left->setMatchedAgainst($right);

                        return $left;
                    }
                }

                if (($left->type === $right->type) && ($left->subtype === $right->subtype || ($right->subtype === '*' || $left->subtype === '*'))
                    && ($left->format === $right->format || $right->format === '*' || $left->format === '*') && $this->matchAcceptParams(match1: $left, match2: $right)) {
                    $left->setMatchedAgainst($right);

                    return $left;
                }
            }
        }

        return false;
    }

    /**
     * Return a match where all parameters in argument #1 match those in argument #2
     *
     * @param array $match1
     * @param array $match2
     * @return bool|array
     */
    protected function matchAcceptParams(array $match1, array $match2): bool|array
    {
        foreach ($match2->params as $key => $value) {
            if (isset($match1->params[$key])) {
                if (strpos(haystack: $value, needle: '-')) {
                    preg_match(
                        pattern: '/^(?|([^"-]*)|"([^"]*)")-(?|([^"-]*)|"([^"]*)")\z/',
                        subject: $value,
                        matches: $pieces
                    );

                    if (
                        count(value: $pieces) === 3
                        && (version_compare(version1: $pieces[1], version2: $match1->params[$key], operator: '<=')
                            xor version_compare(version1: $pieces[2], version2: $match1->params[$key], operator: '>='))
                    ) {
                        return false;
                    }
                } elseif (strpos(haystack: $value, needle: '|')) {
                    $options = explode(separator: '|', string: $value);
                    $good    = false;
                    foreach ($options as $option) {
                        if ($option === $match1->params[$key]) {
                            $good = true;
                            break;
                        }
                    }

                    if (!$good) {
                        return false;
                    }
                } elseif ($match1->params[$key] !== $value) {
                    return false;
                }
            }
        }

        return $match1;
    }

    /**
     * Add a key/value combination to the internal queue
     *
     * @param stdClass $value
     * @return void
     */
    protected function addFieldValuePartToQueue(stdClass $value): void
    {
        $this->fieldValueParts[] = $value;
        $this->sorted            = false;
    }

    /**
     * Sort the internal Field Value Parts
     *
     * @return void
     * @see rfc2616 sect 14.1
     * Media ranges can be overridden by more specific media ranges or
     * specific media types. If more than one media range applies to a given
     * type, the most specific reference has precedence. For example,
     *
     * Accept: text/*, text/html, text/html;level=1, * /*
     *
     * have the following precedence:
     *
     * 1) text/html;level=1
     * 2) text/html
     * 3) text/*
     * 4) * /*
     *
     */
    protected function sortFieldValueParts(): void
    {
        $sort = static function (object $a, object $b): int {
            // If A has higher precedence than B, return -1.
            if ($a->priority > $b->priority) {
                return -1;
            } elseif ($a->priority < $b->priority) {
                return 1;
            }

            // Asterisks
            $values = ['type', 'subtype', 'format'];
            foreach ($values as $value) {
                if ($a->$value === '*' && $b->$value !== '*') {
                    return 1;
                } elseif ($b->$value === '*' && $a->$value !== '*') {
                    return -1;
                }
            }

            if ($a->type === 'application' && $b->type !== 'application') {
                return -1;
            } elseif ($b->type === 'application' && $a->type !== 'application') {
                return 1;
            }

            // @todo count number of dots in case of type==application in subtype

            // So far they're still the same. Longest string length may be more specific
            if (strlen(string: $a->raw) === strlen(string: $b->raw)) {
                return 0;
            }
            return strlen(string: $a->raw) > strlen(string: $b->raw) ? -1 : 1;
        };

        usort(array: $this->fieldValueParts, callback: $sort);
        $this->sorted = true;
    }

    /**
     * @return array with all the keys, values and parameters this header represents:
     */
    public function getPrioritized(): array
    {
        if (!$this->sorted) {
            $this->sortFieldValueParts();
        }

        return $this->fieldValueParts;
    }
}
