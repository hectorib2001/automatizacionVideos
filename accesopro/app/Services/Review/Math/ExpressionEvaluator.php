<?php

namespace App\Services\Review\Math;

use Brick\Math\BigRational;

/**
 * Evalúa expresiones aritméticas simples: enteros, decimales, +, -, *, /,
 * potencias ^ (exponente entero, negativo incluido), paréntesis y sqrt(n).
 * Ver docs/REVISOR.md ("MathVerifier") para la gramática admitida.
 */
final class ExpressionEvaluator
{
    /** @var list<string> */
    private array $tokens;

    private int $pos = 0;

    public function evaluate(string $expression): NumericValue
    {
        $this->tokens = $this->tokenize($expression);
        $this->pos = 0;

        $value = $this->parseExpr();

        if ($this->pos !== count($this->tokens)) {
            throw new ExpressionSyntaxException("Carácter inesperado cerca de '{$this->peek()}'.");
        }

        return $value;
    }

    /** expr := term (('+'|'-') term)* */
    private function parseExpr(): NumericValue
    {
        $value = $this->parseTerm();

        while (in_array($this->peek(), ['+', '-'], true)) {
            $op = $this->next();
            $right = $this->parseTerm();
            $value = $op === '+' ? $value->plus($right) : $value->minus($right);
        }

        return $value;
    }

    /** term := power (('*'|'/') power)* */
    private function parseTerm(): NumericValue
    {
        $value = $this->parsePower();

        while (in_array($this->peek(), ['*', '/'], true)) {
            $op = $this->next();
            $right = $this->parsePower();
            $value = $op === '*' ? $value->multipliedBy($right) : $value->dividedBy($right);
        }

        return $value;
    }

    /** power := unary ('^' unary)? */
    private function parsePower(): NumericValue
    {
        $value = $this->parseUnary();

        if ($this->peek() === '^') {
            $this->next();
            $exponent = $this->parseUnary();

            return $value->power($exponent);
        }

        return $value;
    }

    /** unary := '-' unary | primary */
    private function parseUnary(): NumericValue
    {
        if ($this->peek() === '-') {
            $this->next();

            return $this->parseUnary()->negated();
        }

        return $this->parsePrimary();
    }

    /** primary := NUMBER | '(' expr ')' | 'sqrt' '(' expr ')' */
    private function parsePrimary(): NumericValue
    {
        $token = $this->peek();

        if ($token === null) {
            throw new ExpressionSyntaxException('Expresión incompleta.');
        }

        if ($token === '(') {
            $this->next();
            $value = $this->parseExpr();
            $this->expect(')');

            return $value;
        }

        if ($token === 'sqrt') {
            $this->next();
            $this->expect('(');
            $value = $this->parseExpr();
            $this->expect(')');

            return $value->sqrt();
        }

        if (is_numeric($token)) {
            $this->next();

            return NumericValue::exact(BigRational::of($token));
        }

        throw new ExpressionSyntaxException("Token inesperado: '{$token}'.");
    }

    private function peek(): ?string
    {
        return $this->tokens[$this->pos] ?? null;
    }

    private function next(): string
    {
        return $this->tokens[$this->pos++];
    }

    private function expect(string $token): void
    {
        if ($this->peek() !== $token) {
            throw new ExpressionSyntaxException("Se esperaba '{$token}' cerca de '{$this->peek()}'.");
        }

        $this->next();
    }

    /** @return list<string> */
    private function tokenize(string $expression): array
    {
        $pattern = '/\s+|(\d+\.\d+|\d+)|([a-zA-Z]+)|([+\-*\/^()])/';
        $tokens = [];
        $offset = 0;
        $length = strlen($expression);

        while ($offset < $length) {
            if (! preg_match($pattern, $expression, $m, PREG_UNMATCHED_AS_NULL, $offset) || $m[0] === '') {
                throw new ExpressionSyntaxException("No se pudo interpretar: '{$expression}'.");
            }

            if (strpos($expression, $m[0], $offset) !== $offset) {
                throw new ExpressionSyntaxException("Carácter no admitido cerca de '".substr($expression, $offset, 5)."'.");
            }

            $matched = $m[0];

            if (trim($matched) !== '') {
                $tokens[] = $m[1] ?? $m[2] ?? $m[3] ?? $matched;
            }

            $offset += strlen($matched);
        }

        return $tokens;
    }
}
