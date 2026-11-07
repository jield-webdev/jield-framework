<?php

declare(strict_types=1);

namespace Jield\Search\Solr\Expression;

use DateTime;
use DateTimeZone;
use JetBrains\PhpStorm\Pure;
use Jield\Search\Solr\Expression\Exception\InvalidArgumentException;
use Jield\Search\Solr\ExpressionInterface;
use Jield\Search\Solr\Util;

use Stringable;
use function array_filter;
use function array_pop;
use function array_shift;
use function array_unshift;
use function end;
use function func_get_args;
use function is_array;
use function is_bool;
use function is_iterable;
use function is_numeric;
use function is_object;
use function is_string;
use function trim;

class ExpressionBuilder
{
    private DateTimeZone|string $defaultTimezone = 'UTC';

    /**
     * Set default timezone for the Solr search server
     *
     * The default timezone is used to convert date queries. You can either
     * pass a string (like "Europe/Berlin") or a DateTimeZone object.
     *
     * @throws InvalidArgumentException
     */
    public function setDefaultTimezone(DateTimeZone|string $timezone): void
    {
        $this->defaultTimezone = $timezone;
    }

    /**
     * Create phrase expression: "term1 term2"
     */
    #[Pure]
    public function phrase(?string $str): ?ExpressionInterface
    {
        if ($this->ignore(expr: $str)) {
            return null;
        }

        return new PhraseExpression(expr: $str);
    }

    /** @psalm-assert-if-false !null $expr */
    private function ignore(mixed $expr): bool
    {
        return $expr === null || is_string(value: $expr) && trim(string: $expr) === '';
    }

    /**
     * Create boost expression: <expr>^<boost>
     *
     * @param string|ExpressionInterface|null $expr
     */
    #[Pure]
    public function boost(ExpressionInterface|string|null $expr, ?float $boost): ?ExpressionInterface
    {
        if ($this->ignore(expr: $expr) or $this->ignore(expr: $boost)) {
            return null;
        }

        return new BoostExpression(boost: $boost, expr: $expr);
    }

    /**
     * Create proximity match expression: "<word1> <word2>"~<proximity>
     *
     * @param mixed $proximity
     */
    public function prx(ExpressionInterface|string|null $word = null, mixed $proximity = null): ?ExpressionInterface
    {
        $arguments = func_get_args();
        /** @var mixed $proximityElement */
        $proximityElement = array_pop(array: $arguments);

        $arguments = $this->flatten(collection: $arguments);

        if (!$arguments) {
            return null;
        }

        $words = array_map(static function (mixed $word): string {
            if (!is_scalar($word) && !$word instanceof Stringable && $word !== null) {
                throw new InvalidArgumentException('Proximity words must be scalar or stringable.');
            }
            return (string) $word;
        }, $arguments);
        if (!is_int($proximityElement)) {
            throw new InvalidArgumentException('Proximity must be an integer.');
        }
        return new ProximityExpression(words: $words, proximity: $proximityElement);
    }

    /** @return list<mixed> */
    private function flatten(mixed $collection): array
    {
        $stack = [$collection];
        $result = [];

        while (!empty($stack)) {
            /** @var mixed $item */
            $item = array_shift(array: $stack);

            if (is_iterable(value: $item)) {
                foreach ($item as $element) {
                    array_unshift($stack, $element);
                }
            } else {
                array_unshift($result, $item);
            }
        }

        return $result;
    }

    /**
     * Create fuzzy expression: <expr>~<similarity>
     *
     * @param string|ExpressionInterface|null $expr
     * @param float $similarity Similarity between 0.0 und 1.0
     */
    #[Pure]
    public function fzz(ExpressionInterface|string|null $expr, ?float $similarity = null): ?ExpressionInterface
    {
        if ($this->ignore(expr: $expr)) {
            return null;
        }

        return new FuzzyExpression(expr: $expr, similarity: $similarity);
    }

    /**
     * Range query expression (exclusive start/end): {start TO end}
     */
    #[Pure]
    public function btwnRange(
        ExpressionInterface|float|int|string|null $start = null,
        ExpressionInterface|float|int|string|null $end = null,
    ): ExpressionInterface {
        return new RangeExpression(start: $start, end: $end, inclusive: false);
    }

    /**
     * Create wildcard expression: <prefix>?, <prefix>*, <prefix>?<suffix> or <prefix>*<suffix>
     *
     * @param ExpressionInterface|string $suffix
     */
    public function wild(?string $wildcard = '*', ExpressionInterface|string $suffix = '*'): ?ExpressionInterface
    {
        $wildcard = strtolower(string: (string) $wildcard);
        //$wildcard = str_replace(' ', '\ ', $wildcard);

        $wildcard = Util::escape(value: $wildcard);
        $prefix = '*';

        if ($wildcard === '') {
            return null;
        }

        return new WildcardExpression(wildcard: $wildcard, prefix: $prefix, suffix: $suffix);
    }

    /**
     * Create bool, prohibited expression using the NOT notation, usable in OR/AND expressions:
     * (*:* NOT <expr>), e.g. (*:* NOT fieldName:*)
     *
     * @param string|ExpressionInterface|null $expr
     * @return ExpressionInterface|null
     */
    #[Pure]
    public function not(ExpressionInterface|string|null $expr): BooleanExpression|ExpressionInterface|null
    {
        if ($this->ignore(expr: $expr)) {
            return null;
        }

        return new BooleanExpression(
            operator: BooleanExpression::OPERATOR_PROHIBITED,
            expr: $expr,
            useNotNotation: true,
        );
    }

    /**
     * Create bool expression
     *
     *      true => required (+)
     *      false => prohibited (-)
     *      null => neutral (<empty>)
     *
     * @param string|ExpressionInterface|null $expr
     * @param bool|null $operator @codingStandardsIgnoreLine
     * @return ExpressionInterface|string|null
     */
    public function bool(
        ExpressionInterface|string|null $expr,
        ?bool $operator,
    ): BooleanExpression|ExpressionInterface|string|null { // @codingStandardsIgnoreLine
        if ($operator === null) {
            return $expr;
        }

        if ($operator) {
            return $this->req(expr: $expr);
        } else {
            return $this->prhb(expr: $expr);
        }
    }

    /**
     * Create bool, required expression: +<expr>
     *
     * @param string|ExpressionInterface|null $expr
     * @return ExpressionInterface|null
     */
    #[Pure]
    public function req(ExpressionInterface|string|null $expr): BooleanExpression|ExpressionInterface|null
    {
        if ($this->ignore(expr: $expr)) {
            return null;
        }

        return new BooleanExpression(operator: BooleanExpression::OPERATOR_REQUIRED, expr: $expr);
    }

    /**
     * Create bool, prohibited expression: -<expr>
     *
     * @param string|ExpressionInterface|null $expr
     * @return ExpressionInterface|null
     */
    #[Pure]
    public function prhb(ExpressionInterface|string|null $expr): BooleanExpression|ExpressionInterface|null
    {
        if ($this->ignore(expr: $expr)) {
            return null;
        }

        return new BooleanExpression(operator: BooleanExpression::OPERATOR_PROHIBITED, expr: $expr);
    }

    /**
     * Create AND grouped expression: (<expr1> AND <expr2> AND <expr3>)
     *
     * @param mixed ...$args
     */
    public function andX(...$args): ?ExpressionInterface
    {
        $args = $this->parseCompositeArgs(args: $args)[0];

        if (!$args) {
            return null;
        }

        return new GroupExpression(expressions: $args, type: CompositeExpression::TYPE_AND);
    }

    /**
     * @param array<array-key, mixed> $args
     * @return array{0: array<array-key, mixed>|false, 1: ?string}
     */
    private function parseCompositeArgs(array $args): array
    {
        $args = $this->flatten(collection: $args);
        $type = CompositeExpression::TYPE_SPACE;

        /** @var mixed $last */
        $last = end(array: $args);
        if ((is_string($last) || $last === null) && CompositeExpression::isValidType(type: $last)) {
            array_pop(array: $args);
            $type = $last;
        }

        $args = array_filter(array: $args, callback: $this->permit(...));

        if (!$args) {
            return [false, $type];
        }

        return [$args, $type];
    }

    /**
     * Create OR grouped expression: (<expr1> OR <expr2> OR <expr3>)
     *
     * @param mixed ...$args
     */
    public function orX(...$args): ?ExpressionInterface
    {
        $args = $this->parseCompositeArgs(args: $args)[0];

        if (!$args) {
            return null;
        }

        return new GroupExpression(expressions: $args, type: CompositeExpression::TYPE_OR);
    }

    /**
     * Returns a query "*:*" which means find all if $expr is empty
     *
     * @param string|ExpressionInterface|null $expr
     */
    public function all(ExpressionInterface|string|null $expr = null): mixed
    {
        if ($this->permit(expr: $expr)) {
            return $expr;
        }

        return new FieldExpression(field: new Expression('*'), expr: new Expression('*'));
    }

    #[Pure]
    /** @psalm-assert-if-true !null $expr */
    private function permit(mixed $expr): bool
    {
        return !$this->ignore(expr: $expr);
    }

    /**
     * Create field expression: <field>:<expr>
     * of in an array $expr is given: <field>:(<expr1> <expr2> <expr3>...)
     */
    #[Pure]
    public function field(
        ExpressionInterface|string $field,
        array|ExpressionInterface|string|int|bool|null $expr,
    ): ?ExpressionInterface {
        if (is_array(value: $expr)) {
            $expr = $this->grp(expr: $expr);
        } elseif ($this->ignore(expr: $expr)) {
            return null;
        }

        if ($expr === null) {
            return null;
        }

        return new FieldExpression(
            field: $field,
            expr: is_bool($expr) ? ($expr ? 'true' : 'false') : (is_int($expr) ? (string) $expr : $expr),
        );
    }

    /**
     * Create grouped expression: (<expr1> <expr2> <expr3>)
     *
     * @param array<array-key, mixed>|ExpressionInterface|string|null $expr
     * @param string|null $type
     */
    #[Pure]
    public function grp(
        array|ExpressionInterface|string|null $expr = null,
        ?string $type = CompositeExpression::TYPE_SPACE,
    ): ?ExpressionInterface {
        if (empty($expr)) {
            return null;
        }

        return new GroupExpression(expressions: is_array($expr) ? $expr : [$expr], type: $type);
    }

    /**
     * Return string treated as literal (unescaped, unquoted)
     *
     * @param string|ExpressionInterface|null $expr
     */
    #[Pure]
    public function lit(ExpressionInterface|string|null $expr): ?ExpressionInterface
    {
        if ($this->ignore(expr: $expr)) {
            return null;
        }

        return new Expression(expr: $expr);
    }

    #[Pure]
    public function number(ExpressionInterface|string $field, mixed $expr): ?ExpressionInterface
    {
        if (!is_numeric(value: $expr)) {
            return null;
        }

        return new FieldExpression(field: $field, expr: new Expression(expr: (string) $expr));
    }

    /**
     * Create term expression: <expr>
     *
     * @param string|ExpressionInterface|null $expr
     */
    #[Pure]
    public function eq(ExpressionInterface|string|null $expr): ?ExpressionInterface
    {
        if ($this->ignore(expr: $expr)) {
            return null;
        }

        if ($expr instanceof ExpressionInterface) {
            return $expr;
        }

        return new PhraseExpression(expr: $expr);
    }

    /**
     * Create a date expression for a specific day
     *
     * @param mixed $date
     */
    #[Pure]
    public function day(mixed $date = null): ?ExpressionInterface
    {
        if (!$date instanceof DateTime) {
            return null;
        }

        return $this->range(start: $this->startOfDay(date: $date), end: $this->endOfDay(date: $date));
    }

    /**
     * Range query expression (inclusive start/end): [start TO end]
     */
    #[Pure]
    public function range(
        ExpressionInterface|float|int|string|null $start = null,
        ExpressionInterface|float|int|string|null $end = null,
        bool $inclusive = true,
    ): ExpressionInterface {
        return new RangeExpression(start: $start, end: $end, inclusive: $inclusive);
    }

    /**
     * Expression for the start of the given date
     */
    #[Pure]
    public function startOfDay(
        ?DateTime $date = null,
        DateTimeZone|string|false $timezone = false,
    ): ?ExpressionInterface {
        if ($date === null) {
            return null;
        }

        return new DateTimeExpression(
            date: $date,
            format: DateTimeExpression::FORMAT_START_OF_DAY,
            timezone: $timezone === false ? $this->defaultTimezone : $timezone,
        );
    }

    /**
     * Expression for the end of the given date
     */
    #[Pure]
    public function endOfDay(?DateTime $date = null, DateTimeZone|string|false $timezone = false): ?ExpressionInterface
    {
        if (!$date) {
            return null;
        }

        return new DateTimeExpression(
            date: $date,
            format: DateTimeExpression::FORMAT_END_OF_DAY,
            timezone: $timezone === false ? $this->defaultTimezone : $timezone,
        );
    }

    /**
     * Create a range between two dates (one side may be unlimited which is indicated by passing null)
     */
    #[Pure]
    public function dateRange(
        ?DateTime $from = null,
        ?DateTime $to = null,
        bool $inclusive = true,
        DateTimeZone|string|false $timezone = 'Europe/Amsterdam',
    ): ?ExpressionInterface {
        if ($from === null && $to === null) {
            return null;
        }

        return $this->range(
            start: $this->lit(expr: $this->date(date: $from, timezone: $timezone)),
            end: $this->lit(expr: $this->date(date: $to, timezone: $timezone)),
            inclusive: $inclusive,
        );
    }

    #[Pure]
    public function date(?DateTime $date = null, DateTimeZone|string|false $timezone = false): ExpressionInterface
    {
        if ($date === null) {
            return new Expression(expr: '*');
        }

        return new DateTimeExpression(
            date: $date,
            format: DateTimeExpression::FORMAT_DEFAULT,
            timezone: $timezone === false ? $this->defaultTimezone : $timezone,
        );
    }

    /**
     * Create a function expression of name $function
     *
     * You can either pass an array of parameters, a single parameter or a ParameterExpression
     *
     * @param array|ExpressionInterface|string|null $parameters
     */
    #[Pure]
    public function func(
        string $function,
        array|ExpressionInterface|string|null $parameters = null,
    ): ExpressionInterface {
        return new FunctionExpression(function: $function, parameters: $parameters);
    }

    /**
     * Create a function parameters expression
     */
    public function params(mixed ...$parameters): ExpressionInterface
    {
        $parameters = $this->flatten(collection: $parameters);

        return new ParameterExpression(parameters: $parameters);
    }

    /**
     * @param mixed|mixed[] $params
     * @param mixed $shortForm
     */
    public function localParams(string $type, mixed $params = [], mixed $shortForm = true): ?ExpressionInterface
    {
        $additional = null;

        if (!is_bool(value: $shortForm)) {
            $additional = $shortForm;
            $shortForm = true;
        } elseif (!is_array(value: $params)) {
            $additional = $params;
            $params = [];
        }

        if (!is_array($params)) {
            throw new InvalidArgumentException('Local parameter options must be an array.');
        }

        $useShortForm = is_bool($shortForm) ? $shortForm : true;

        if ($additional !== null) {
            return $this->comp(expr: [
                new LocalParamsExpression(type: $type, params: $params, shortForm: $useShortForm),
                $additional,
            ], type: null);
        }

        return new LocalParamsExpression(type: $type, params: $params, shortForm: $useShortForm);
    }

    /**
     * Create composite expression: <expr1> <expr2> <expr3>
     *
     * @param array<array-key, mixed>|ExpressionInterface|string|null $expr
     */
    public function comp(
        array|ExpressionInterface|string|null $expr = null,
        ?string $type = CompositeExpression::TYPE_SPACE,
    ): ?ExpressionInterface {
        [$args, $type] = $this->parseCompositeArgs(args: func_get_args());

        if (!$args) {
            return null;
        }

        return new CompositeExpression(expressions: $args, type: $type);
    }

    /** @param mixed[] $additionalParams */
    #[Pure]
    public function geofilt(
        string $field,
        ?GeolocationExpression $geolocation = null,
        ?int $distance = null,
        array $additionalParams = [],
    ): ExpressionInterface {
        return new GeofiltExpression(
            field: $field,
            geolocation: $geolocation,
            distance: $distance,
            additionalParams: $additionalParams,
        );
    }

    /**
     * Create a geo location expression: "<latitude>,<longitude>" using the given precision
     */
    #[Pure]
    public function latLong(float $latitude, float $longitude, int $precision = 12): ExpressionInterface
    {
        return new GeolocationExpression(latitude: $latitude, longitude: $longitude, precision: $precision);
    }

    /** @param string|ExpressionInterface|null $expr */
    public function noCache(ExpressionInterface|string|null $expr = null): ?ExpressionInterface
    {
        if ($this->ignore(expr: $expr)) {
            return null;
        }

        return $this->comp(expr: [$this->shortLocalParams(tag: 'cache', value: false), $expr], type: null);
    }

    #[Pure]
    private function shortLocalParams(string $tag, mixed $value): LocalParamsExpression
    {
        return new LocalParamsExpression(type: $tag, params: [$tag => $value], shortForm: true);
    }

    /** @param string|ExpressionInterface|null $expr */
    public function tag(string $tagName, ExpressionInterface|string|null $expr = null): ?ExpressionInterface
    {
        if ($this->ignore(expr: $expr)) {
            return null;
        }

        return $this->comp(expr: [$this->shortLocalParams(tag: 'tag', value: $tagName), $expr], type: null);
    }

    /** @param string|ExpressionInterface|null $expr */
    public function excludeTag(string $tagName, ExpressionInterface|string|null $expr = null): ?ExpressionInterface
    {
        if ($this->ignore(expr: $expr)) {
            return null;
        }

        return $this->comp(expr: [$this->shortLocalParams(tag: 'ex', value: $tagName), $expr], type: null);
    }

    /**
     * Create a search query expression following Google-like rules:
     * - Strings between quotes are searched as full phrases
     * - Non-quoted parts are searched as individual words
     *
     * @param string $searchQuery The search query string
     * @return ExpressionInterface|null The resulting expression
     */
    public function searchQuery(string $searchQuery): ?ExpressionInterface
    {
        if (trim($searchQuery) === '') {
            return new Expression(expr: '*');
        }

        $searchQuery = trim(string: $searchQuery);

        if ($searchQuery === '*') {
            return new Expression(expr: '*');
        }

        // If the entire query is already quoted and doesn't contain any other quotes, treat it as a phrase
        if (
            strlen(string: $searchQuery) >= 2
            && $searchQuery[0] === '"'
            && $searchQuery[strlen(string: $searchQuery) - 1] === '"'
            && substr_count(haystack: $searchQuery, needle: '"') === 2
        ) {
            $content = substr(string: $searchQuery, offset: 1, length: -1);
            if (empty(trim(string: $content))) {
                return null;
            }
            return new SearchQueryPhraseExpression(expr: $content);
        }

        // Use a regex-based approach to handle quoted phrases and individual words
        $expressions = [];

        // First, handle quoted phrases (including unclosed quotes)
        $pattern = '/"([^"]*)"?/';
        $matches = [];
        preg_match_all(pattern: $pattern, subject: $searchQuery, matches: $matches);

        // Add each quoted phrase as a SearchQueryPhraseExpression
        foreach ($matches[1] as $phrase) {
            if (empty(trim(string: $phrase))) {
                continue;
            }

            $expressions[] = new SearchQueryPhraseExpression(expr: $phrase);
        }

        // Remove all quoted phrases from the search query
        $remainingText = preg_replace(pattern: $pattern, replacement: '', subject: $searchQuery);

        // Handle remaining non-quoted words
        $words = preg_split(
            pattern: '/\s+/',
            subject: trim(string: $remainingText ?? ''),
            limit: -1,
            flags: PREG_SPLIT_NO_EMPTY,
        );
        foreach ($words ?: [] as $word) {
            // Escape special characters according to SOLR rules
            $escapedWord = Util::escape(value: $word);
            $expressions[] = new Expression(expr: $escapedWord);
        }

        // If no expressions were created, return null
        if (empty($expressions)) {
            return null;
        }

        // If only one expression, return it directly
        if (count(value: $expressions) === 1) {
            return $expressions[0];
        }

        // Otherwise, combine expressions with OR
        return new GroupExpression(expressions: $expressions, type: CompositeExpression::TYPE_OR);
    }

    /**
     * Add words from a string to the expressions array
     *
     * @param list<ExpressionInterface> $expressions Array to add the resulting expressions to
     * @param string $text The text to process
     */
    private function _addWordsToExpressions(array &$expressions, string $text): void
    {
        $words = preg_split(pattern: '/\s+/', subject: trim(string: $text), limit: -1, flags: PREG_SPLIT_NO_EMPTY);

        foreach ($words ?: [] as $word) {
            if (empty($word)) {
                continue;
            }

            // Escape special characters according to SOLR rules
            $escapedWord = Util::escape(value: $word);
            $expressions[] = new Expression(expr: $escapedWord);
        }
    }

    /**
     * Create an advanced search query expression with field-specific search, wildcard options, and boosting
     *
     * @param string $searchQuery The search query string
     * @param list<string> $fields Fields to search on
     * @param array<string, float> $boostFields Field boosting values
     * @return ExpressionInterface|null The resulting expression
     */
    public function advancedSearchQuery(
        ?string $searchQuery,
        array $fields = [],
        array $boostFields = [],
    ): ?ExpressionInterface {
        if ($searchQuery === null || in_array(trim($searchQuery), ['', '*', '*:*'], true)) {
            return $this->all();
        }

        // First, get the base search query expression
        $baseExpression = $this->searchQuery(searchQuery: $searchQuery);

        if ($baseExpression === null) {
            return null;
        }

        // If no fields specified, return the base expression
        if (empty($fields)) {
            return $baseExpression;
        }

        // Create field-specific expressions
        $fieldExpressions = [];

        foreach ($fields as $field) {
            // If the field is a numeric key, it means no boost was specified
            $fieldBoost = $boostFields[$field] ?? null;

            // Check for _sort suffix - use wildcards and medium boost (between 1 and 3)
            // If no boost was specified, use a medium boost value (2)
            if (str_ends_with(haystack: $field, needle: '_sort') && $fieldBoost === null) {
                $fieldBoost = 2.0;
            } // Check for _search suffix - use wildcards but no boost

            // Create a copy of the base expression for this field
            $fieldExpression = $this->processExpressionForField(
                expression: $baseExpression,
                field: $field,
                boost: $fieldBoost,
            );

            if ($fieldExpression !== null) {
                $fieldExpressions[] = $fieldExpression;
            }
        }

        // If no field expressions were created, return null
        if (empty($fieldExpressions)) {
            return null;
        }

        // If only one field expression, return it directly
        if (count(value: $fieldExpressions) === 1) {
            return $fieldExpressions[0];
        }

        // Otherwise, combine field expressions with OR
        return new GroupExpression(expressions: $fieldExpressions, type: CompositeExpression::TYPE_OR);
    }

    /**
     * Process an expression for a specific field, applying wildcards and boosting as needed
     *
     * @param ExpressionInterface $expression The expression to process
     * @param string $field The field to search on
     * @param float|null $boost The boost value to apply
     * @return ExpressionInterface|null The processed expression
     */
    private function processExpressionForField(
        ExpressionInterface $expression,
        string $field,
        ?float $boost = null,
    ): ?ExpressionInterface {
        // Handle different expression types
        if ($expression instanceof GroupExpression) {
            // For group expressions, process each sub-expression
            $subExpressions = [];

            // Get the expressions from the group
            $groupExprStr = (string) $expression;
            $groupType = str_contains(haystack: $groupExprStr, needle: ' OR ')
                ? CompositeExpression::TYPE_OR
                : CompositeExpression::TYPE_AND;

            // Extract expressions from the group
            $matches = [];
            if (preg_match(pattern: '/^\((.*)\)$/', subject: $groupExprStr, matches: $matches)) {
                $content = $matches[1];

                // Split by the group type
                $separator = $groupType === CompositeExpression::TYPE_OR ? ' OR ' : ' AND ';

                $parts = explode(separator: $separator, string: $content);

                foreach ($parts as $part) {
                    if (trim(string: $part) === '') {
                        continue;
                    }

                    // Create appropriate expression based on the part
                    if (
                        str_starts_with(haystack: $part, needle: '"')
                        && strrpos(haystack: $part, needle: '"') === (strlen(string: $part) - 1)
                    ) {
                        // It's a phrase
                        $subExpr = new SearchQueryPhraseExpression(expr: substr(string: $part, offset: 1, length: -1));
                    } else {
                        // It's a regular expression
                        $subExpr = new Expression(expr: $part);
                    }

                    // Apply wildcards if needed for non-phrase expressions
                    if (!$subExpr instanceof SearchQueryPhraseExpression) {
                        $exprStr = (string) $subExpr;

                        $subExpr = new WildcardExpression(wildcard: $exprStr, prefix: '*', suffix: '*');
                    }

                    // Create a field expression for this sub-expression
                    $subExpressions[] = new FieldExpression(field: $field, expr: $subExpr);
                }
            }

            // If no sub-expressions were created, return null
            if (empty($subExpressions)) {
                return null;
            }

            // Create a new group with the processed sub-expressions
            $fieldExpression = new GroupExpression(expressions: $subExpressions, type: $groupType);

            // Apply boost if specified
            if ($boost !== null) {
                return new BoostExpression(boost: $boost, expr: $fieldExpression);
            }

            return $fieldExpression;
        }

        if ($expression instanceof SearchQueryPhraseExpression) {
            // For phrase expressions, create a field expression with the phrase
            $fieldExpression = new FieldExpression(field: $field, expr: $expression);

            // Apply boost if specified
        } else {
            // For regular expressions, apply wildcards if needed
            // Create a wildcard expression
            $exprStr = (string) $expression;
            $wildcardExpr = new WildcardExpression(wildcard: $exprStr, prefix: '*', suffix: '*');
            $fieldExpression = new FieldExpression(field: $field, expr: $wildcardExpr);
        }
        if ($boost !== null) {
            return new BoostExpression(boost: $boost, expr: $fieldExpression);
        }
        return $fieldExpression;
    }
}
