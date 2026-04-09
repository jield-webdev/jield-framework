<?php

declare(strict_types=1);

namespace Jield\I18n\Translator\Plural;

use Jield\I18n\Exception;

use function assert;
use function ctype_digit;
use function sprintf;

/**
 * Plural rule parser.
 *
 * This plural rule parser is implemented after the article "Top Down Operator Precedence"
 *
 * @internal
 *
 * @link https://crockford.com/javascript/tdop/tdop.html.
 *
 * @psalm-internal Jield\I18n
 * @psalm-internal JieldTest\I18n
 */
final class Parser
{
    /**
     * Table of symbols.
     *
     * @var array<string, Symbol>
     */
    private array $symbolTable = [];
    private Symbol $currentToken;
    private int $currentPos;

    /**
     * Create a new plural parser.
     */
    private function __construct(
        private string $string,
    ) {
        $this->currentPos = 0;
        $this->populateSymbolTable();
        $this->currentToken = $this->getNextToken();
    }

    /**
     * Parse a string
     */
    public static function parse(string $string): Symbol
    {
        $instance = new self(string: $string . "\0");

        return $instance->expression();
    }

    /**
     * Populate the symbol table.
     */
    private function populateSymbolTable(): void
    {
        // Ternary operators
        $this->registerSymbol(id: '?', leftBindingPower: 20)->setLeftDenotationGetter(
            getter: static function (Symbol $self, Symbol $left): Symbol {
                $self->first  = $left;
                $self->second = $self->parser->expression();
                $self->parser->advance(id: ':');
                $self->third = $self->parser->expression();
                return $self;
            }
        );
        $this->registerSymbol(id: ':');

        // Boolean operators
        $this->registerLeftInfixSymbol(id: '||', leftBindingPower: 30);
        $this->registerLeftInfixSymbol(id: '&&', leftBindingPower: 40);

        // Equal operators
        $this->registerLeftInfixSymbol(id: '==', leftBindingPower: 50);
        $this->registerLeftInfixSymbol(id: '!=', leftBindingPower: 50);

        // Compare operators
        $this->registerLeftInfixSymbol(id: '>', leftBindingPower: 50);
        $this->registerLeftInfixSymbol(id: '<', leftBindingPower: 50);
        $this->registerLeftInfixSymbol(id: '>=', leftBindingPower: 50);
        $this->registerLeftInfixSymbol(id: '<=', leftBindingPower: 50);

        // Add operators
        $this->registerLeftInfixSymbol(id: '-', leftBindingPower: 60);
        $this->registerLeftInfixSymbol(id: '+', leftBindingPower: 60);

        // Multiply operators
        $this->registerLeftInfixSymbol(id: '*', leftBindingPower: 70);
        $this->registerLeftInfixSymbol(id: '/', leftBindingPower: 70);
        $this->registerLeftInfixSymbol(id: '%', leftBindingPower: 70);

        // Not operator
        $this->registerPrefixSymbol(id: '!', leftBindingPower: 80);

        // Literals
        $this->registerSymbol(id: 'n')->setNullDenotationGetter(
            getter: static fn(Symbol $self): Symbol => $self
        );
        $this->registerSymbol(id: 'number')->setNullDenotationGetter(
            getter: static fn(Symbol $self): Symbol => $self
        );

        // Parentheses
        $this->registerSymbol(id: '(')->setNullDenotationGetter(
            getter: static function (Symbol $self): Symbol {
                $expression = $self->parser->expression();
                $self->parser->advance(id: ')');
                return $expression;
            }
        );
        $this->registerSymbol(id: ')');

        // Eof
        $this->registerSymbol(id: 'eof');
    }

    private function registerLeftInfixSymbol(string $id, int $leftBindingPower): void
    {
        $this->registerSymbol(id: $id, leftBindingPower: $leftBindingPower)->setLeftDenotationGetter(
            getter: static function (Symbol $self, Symbol $left) use ($leftBindingPower) {
                $self->first  = $left;
                $self->second = $self->parser->expression(rightBindingPower: $leftBindingPower);
                return $self;
            }
        );
    }

    private function registerPrefixSymbol(string $id, int $leftBindingPower): void
    {
        $this->registerSymbol(id: $id, leftBindingPower: $leftBindingPower)->setNullDenotationGetter(
            getter: static function (Symbol $self) use ($leftBindingPower) {
                $self->first  = $self->parser->expression(rightBindingPower: $leftBindingPower);
                $self->second = null;
                return $self;
            }
        );
    }

    /**
     * Register a symbol.
     */
    private function registerSymbol(string $id, int $leftBindingPower = 0): Symbol
    {
        $symbol                 = new Symbol(parser: $this, id: $id, leftBindingPower: $leftBindingPower);
        $this->symbolTable[$id] = $symbol;

        return $symbol;
    }

    /**
     * Get a new symbol.
     */
    private function getSymbol(string $id): Symbol
    {
        if (! isset($this->symbolTable[$id])) {
            throw new Exception\RuntimeException(message: sprintf(
                'Unknown symbol "%s"',
                $id,
            ));
        }

        return clone $this->symbolTable[$id];
    }

    /**
     * Parse an expression.
     *
     * @internal
     */
    public function expression(int $rightBindingPower = 0): Symbol
    {
        $token              = $this->currentToken;
        $this->currentToken = $this->getNextToken();
        $left               = $token->getNullDenotation();

        while ($rightBindingPower < $this->currentToken->leftBindingPower) {
            $token              = $this->currentToken;
            $this->currentToken = $this->getNextToken();
            $left               = $token->getLeftDenotation(left: $left);
        }

        return $left;
    }

    /**
     * Advance the current token and optionally check the old token id.
     *
     * @internal
     *
     * @throws Exception\ParseException
     */
    public function advance(string|null $id = null): void
    {
        if ($id !== null && $this->currentToken->id !== $id) {
            throw new Exception\ParseException(
                message: sprintf('Expected token with id %s but received %s', $id, $this->currentToken->id)
            );
        }

        $this->currentToken = $this->getNextToken();
    }

    /**
     * Get the next token.
     *
     * @throws Exception\ParseException
     */
    private function getNextToken(): Symbol
    {
        while ($this->string[$this->currentPos] === ' ' || $this->string[$this->currentPos] === "\t") {
            $this->currentPos++;
        }

        $result = $this->string[$this->currentPos++];
        $value  = null;
        $id     = null;

        switch ($result) {
            case '0':
            case '1':
            case '2':
            case '3':
            case '4':
            case '5':
            case '6':
            case '7':
            case '8':
            case '9':
                while (ctype_digit($this->string[$this->currentPos])) {
                    $result .= $this->string[$this->currentPos++];
                }

                $id    = 'number';
                $value = (int) $result;
                break;

            case '=':
            case '&':
            case '|':
                if ($this->string[$this->currentPos] === $result) {
                    $this->currentPos++;
                    $id = $result . $result;
                }
                break;

            case '!':
            case '<':
            case '>':
                if ($this->string[$this->currentPos] === '=') {
                    $this->currentPos++;
                    $result .= '=';
                }

                $id = $result;
                break;

            case '*':
            case '/':
            case '%':
            case '+':
            case '-':
            case 'n':
            case '?':
            case ':':
            case '(':
            case ')':
                $id = $result;
                break;

            case ';':
            case "\n":
            case "\0":
                $id = 'eof';
                $this->currentPos--;
                break;

            default:
                throw new Exception\ParseException(message: sprintf(
                    'Found invalid character "%s" in input stream',
                    $result
                ));
        }

        assert(assertion: $id !== null);

        $token        = $this->getSymbol(id: $id);
        $token->value = $value;

        return $token;
    }
}
