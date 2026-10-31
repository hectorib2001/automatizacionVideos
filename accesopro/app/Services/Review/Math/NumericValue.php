<?php

namespace App\Services\Review\Math;

use Brick\Math\BigRational;

/**
 * Resultado de evaluar una expresión: exacto (BigRational) mientras solo se usen
 * +, -, *, /, ^ con exponente entero; se vuelve aproximado (float) en cuanto
 * interviene una raíz cuadrada no exacta.
 */
final readonly class NumericValue
{
    private function __construct(
        public ?BigRational $rational,
        public ?float $float,
    ) {}

    public static function exact(BigRational $value): self
    {
        return new self($value, null);
    }

    public static function approx(float $value): self
    {
        return new self(null, $value);
    }

    public function isExact(): bool
    {
        return $this->rational !== null;
    }

    public function toFloat(): float
    {
        return $this->rational?->toFloat() ?? $this->float;
    }

    public function negated(): self
    {
        return $this->isExact()
            ? self::exact($this->rational->negated())
            : self::approx(-$this->float);
    }

    public function plus(self $other): self
    {
        return $this->isExact() && $other->isExact()
            ? self::exact($this->rational->plus($other->rational))
            : self::approx($this->toFloat() + $other->toFloat());
    }

    public function minus(self $other): self
    {
        return $this->isExact() && $other->isExact()
            ? self::exact($this->rational->minus($other->rational))
            : self::approx($this->toFloat() - $other->toFloat());
    }

    public function multipliedBy(self $other): self
    {
        return $this->isExact() && $other->isExact()
            ? self::exact($this->rational->multipliedBy($other->rational))
            : self::approx($this->toFloat() * $other->toFloat());
    }

    public function dividedBy(self $other): self
    {
        if ($this->isExact() && $other->isExact() && ! $other->rational->isZero()) {
            return self::exact($this->rational->dividedBy($other->rational));
        }

        return self::approx($this->toFloat() / $other->toFloat());
    }

    /** Potencia con exponente entero (positivo o negativo). Si el exponente no es
     *  un entero exacto, el resultado se degrada a aproximado. */
    public function power(self $exponent): self
    {
        if ($this->isExact() && $exponent->isExact() && $exponent->rational->getDenominator()->isEqualTo(1)) {
            return self::exact($this->rational->power($exponent->rational->getNumerator()->toInt()));
        }

        return self::approx($this->toFloat() ** $exponent->toFloat());
    }

    public function sqrt(): self
    {
        return self::approx(sqrt($this->toFloat()));
    }

    public function equals(self $other, float $tolerance): bool
    {
        if ($this->isExact() && $other->isExact()) {
            return $this->rational->compareTo($other->rational) === 0;
        }

        return abs($this->toFloat() - $other->toFloat()) <= $tolerance;
    }

    public function toDisplayString(): string
    {
        return $this->isExact() ? $this->rational->toString() : (string) $this->float;
    }
}
