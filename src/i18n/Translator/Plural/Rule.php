<?php

declare(strict_types=1);

namespace Jield\I18n\Translator\Plural;

use Jield\I18n\Exception;

use function abs;
use function assert;
use function floor;
use function is_int;
use function preg_match;
use function sprintf;

/**
 * Plural rule evaluator
 */
final readonly class Rule
{
    private function __construct(
        private int $numPlurals,
        private Node $ast,
    ) {
    }

    /**
     * Evaluate a number and return the plural index.
     *
     * @throws Exception\RangeException
     */
    public function evaluate(int $number): int
    {
        $result = $this->evaluateAstPart(ast: $this->ast, number: abs(num: $number));

        if ($result < 0 || $result >= $this->numPlurals) {
            throw new Exception\RangeException(
                message: sprintf('Calculated result %s is between 0 and %d', $result, $this->numPlurals - 1)
            );
        }

        return $result;
    }

    /**
     * Get number of possible plural forms.
     */
    public function getNumPlurals(): int
    {
        return $this->numPlurals;
    }

    /**
     * Evaluate a part of an ast.
     *
     * @throws Exception\ParseException
     */
    protected function evaluateAstPart(Node $ast, int $number): int
    {
        $first  = $ast->arguments[0] ?? null;
        $second = $ast->arguments[1] ?? null;
        $third  = $ast->arguments[2] ?? null;

        if ($ast->id === 'number') {
            assert(assertion: is_int(value: $first));

            return $first;
        }

        if ($ast->id === 'n') {
            return $number;
        }

        assert(assertion: $first instanceof Node);

        if ($ast->id === '!') {
            $result = $this->evaluateAstPart(ast: $first, number: $number);

            return $result === 0 ? 1 : 0;
        }

        assert(assertion: $second instanceof Node);

        if ($ast->id === '?') {
            assert(assertion: $third instanceof Node);

            return $this->evaluateAstPart(ast: $first, number: $number)
                ? $this->evaluateAstPart(ast: $second, number: $number)
                : $this->evaluateAstPart(ast: $third, number: $number);
        }

        return match ($ast->id) {
            '+' => $this->evaluateAstPart(ast: $first, number: $number)
                   + $this->evaluateAstPart(ast: $second, number: $number),
            '-' => $this->evaluateAstPart(ast: $first, number: $number)
                   - $this->evaluateAstPart(ast: $second, number: $number),
            // Integer division
            '/' => (int) floor(
                num: $this->evaluateAstPart(ast: $first, number: $number)
                / $this->evaluateAstPart(ast: $second, number: $number)
            ),
            '*' => $this->evaluateAstPart(ast: $first, number: $number)
                   * $this->evaluateAstPart(ast: $second, number: $number),
            '%' => $this->evaluateAstPart(ast: $first, number: $number)
                   % $this->evaluateAstPart(ast: $second, number: $number),
            '>' => $this->evaluateAstPart(ast: $first, number: $number)
                   > $this->evaluateAstPart(ast: $second, number: $number)
                   ? 1 : 0,
            '>=' => $this->evaluateAstPart(ast: $first, number: $number)
                   >= $this->evaluateAstPart(ast: $second, number: $number)
                   ? 1 : 0,
            '<' => $this->evaluateAstPart(ast: $first, number: $number)
                   < $this->evaluateAstPart(ast: $second, number: $number)
                   ? 1 : 0,
            '<=' => $this->evaluateAstPart(ast: $first, number: $number)
                   <= $this->evaluateAstPart(ast: $second, number: $number)
                   ? 1 : 0,
            '==' => $this->evaluateAstPart(ast: $first, number: $number)
                   === $this->evaluateAstPart(ast: $second, number: $number)
                   ? 1 : 0,
            '!=' => $this->evaluateAstPart(ast: $first, number: $number)
                   !== $this->evaluateAstPart(ast: $second, number: $number)
                   ? 1 : 0,
            '&&' => $this->evaluateAstPart(ast: $first, number: $number)
                   && $this->evaluateAstPart(ast: $second, number: $number)
                   ? 1 : 0,
            '||' => $this->evaluateAstPart(ast: $first, number: $number)
                   || $this->evaluateAstPart(ast: $second, number: $number)
                   ? 1 : 0,
            default => throw new Exception\ParseException(message: sprintf(
                'Unknown token: %s',
                $ast->id,
            )),
        };
    }

    /**
     * Create a new rule from a string.
     *
     * @throws Exception\ParseException
     */
    public static function fromString(string $string): Rule
    {
        if (! preg_match(pattern: '(nplurals=(?P<nplurals>\d+))', subject: $string, matches: $match)) {
            throw new Exception\ParseException(message: sprintf(
                'Unknown or invalid parser rule: %s',
                $string
            ));
        }

        $numPlurals = (int) $match['nplurals'];

        if (! preg_match(pattern: '(plural=(?P<plural>[^;\n]+))', subject: $string, matches: $match)) {
            throw new Exception\ParseException(message: sprintf(
                'Unknown or invalid parser rule: %s',
                $string
            ));
        }

        $tree = Parser::parse(string: $match['plural']);
        $ast  = self::createAst(symbol: $tree);

        return new self(numPlurals: $numPlurals, ast: $ast);
    }

    /**
     * Create an AST from a tree.
     *
     * Theoretically we could just use the given Symbol, but that one is not
     * so easy to serialize and also takes up more memory.
     */
    protected static function createAst(Symbol $symbol): Node
    {
        $args = [];

        switch ($symbol->id) {
            case 'n':
                break;

            case 'number':
                assert(assertion: is_int(value: $symbol->value));
                $args[] = $symbol->value;
                break;

            case '!':
                assert(assertion: $symbol->first !== null);
                $args[] = self::createAst(symbol: $symbol->first);
                break;

            case '?':
                assert(assertion: $symbol->first !== null);
                assert(assertion: $symbol->second !== null);
                assert(assertion: $symbol->third !== null);
                $args[] = self::createAst(symbol: $symbol->first);
                $args[] = self::createAst(symbol: $symbol->second);
                $args[] = self::createAst(symbol: $symbol->third);
                break;

            default:
                assert(assertion: $symbol->first !== null);
                assert(assertion: $symbol->second !== null);
                $args[] = self::createAst(symbol: $symbol->first);
                $args[] = self::createAst(symbol: $symbol->second);
                break;
        }

        return new Node(id: $symbol->id, arguments: $args);
    }
}
