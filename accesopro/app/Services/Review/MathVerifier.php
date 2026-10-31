<?php

namespace App\Services\Review;

use App\Services\Review\Math\ExpressionEvaluator;
use App\Services\Review\Math\ExpressionSyntaxException;
use Throwable;

/**
 * Recalcula de forma determinista las afirmaciones numéricas que extrae el
 * agente revisor (claims[]). El LLM no es la única garantía de corrección:
 * ver docs/REVISOR.md y docs/DECISIONES.md (D8).
 */
final class MathVerifier
{
    private const DEFAULT_TOLERANCE = 1e-9;

    private const APPROX_TOLERANCE = 1e-2;

    public function __construct(private readonly ExpressionEvaluator $evaluator = new ExpressionEvaluator) {}

    /**
     * @param  array{expresion: string, resultado: string}  $claim
     * @return array{status: 'pass'|'fail'|'unverifiable', detail: string}
     */
    public function verify(array $claim, bool $approx = false): array
    {
        $expresion = $claim['expresion'] ?? '';
        $resultado = $claim['resultado'] ?? '';

        try {
            $left = $this->evaluator->evaluate($expresion);
            $right = $this->evaluator->evaluate($resultado);
        } catch (ExpressionSyntaxException $e) {
            return [
                'status' => 'unverifiable',
                'detail' => "No se pudo verificar '{$expresion}' = '{$resultado}': {$e->getMessage()}",
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'unverifiable',
                'detail' => "Error al evaluar '{$expresion}' = '{$resultado}': {$e->getMessage()}",
            ];
        }

        $tolerance = $approx ? self::APPROX_TOLERANCE : self::DEFAULT_TOLERANCE;

        if ($left->equals($right, $tolerance)) {
            return [
                'status' => 'pass',
                'detail' => "{$expresion} = {$resultado} (calculado: {$left->toDisplayString()}).",
            ];
        }

        return [
            'status' => 'fail',
            'detail' => "{$expresion} no es igual a {$resultado}: el valor real es {$left->toDisplayString()}.",
        ];
    }

    /**
     * @param  list<array{expresion: string, resultado: string, texto_original?: string}>  $claims
     * @return list<array{expresion: string, resultado: string, status: string, detail: string}>
     */
    public function verifyAll(array $claims, bool $approx = false): array
    {
        return array_map(
            fn (array $claim) => [...$claim, ...$this->verify($claim, $approx)],
            $claims,
        );
    }

    public function anyFailed(array $verifiedClaims): bool
    {
        foreach ($verifiedClaims as $claim) {
            if ($claim['status'] === 'fail') {
                return true;
            }
        }

        return false;
    }
}
